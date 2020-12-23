// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Fetch and render dates from timestamps.
 *
 * @module     core/user_date
 * @package    core
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/sessionstorage', 'core/config'],
        function($, Ajax, Storage, Config) {

    var SECONDS_IN_DAY = 86400;

    /** @var {object} promisesCache Store all promises we've seen so far. */
    var promisesCache = {};

    /**
     * Generate a cache key for the given request. The request should
     * have a timestamp and format key.
     *
     * @param {object} request
     * @return {string}
     */
    var getKey = function(request) {
        var language = $('html').attr('lang').replace(/-/g, '_');
        return 'core_user_date/' +
               language + '/' +
               Config.usertimezone + '/' +
               request.timestamp + '/' +
               request.format;
    };

    /**
     * Retrieve a transformed date from the browser's storage.
     *
     * @param {string} key
     * @return {string}
     */
    var getFromLocalStorage = function(key) {
        return Storage.get(key);
    };

    /**
     * Save the transformed date in the browser's storage.
     *
     * @param {string} key
     * @param {string} value
     */
    var addToLocalStorage = function(key, value) {
        Storage.set(key, value);
    };

    /**
     * Check if a key is in the module's cache.
     *
     * @param {string} key
     * @return {bool}
     */
    var inPromisesCache = function(key) {
        return (typeof promisesCache[key] !== 'undefined');
    };

    /**
     * Retrieve a promise from the module's cache.
     *
     * @param {string} key
     * @return {object} jQuery promise
     */
    var getFromPromisesCache = function(key) {
        return promisesCache[key];
    };

    /**
     * Save the given promise in the module's cache.
     *
     * @param {string} key
     * @param {object} promise
     */
    var addToPromisesCache = function(key, promise) {
        promisesCache[key] = promise;
    };

    /**
     * Send a request to the server for each of the required timestamp
     * and format combinations.
     *
     * Resolves the date's deferred with the values returned from the
     * server and saves the value in local storage.
     *
     * @param {array} dates
     * @return {object} jQuery promise
     */
    var loadDatesFromServer = function(dates) {
        var args = dates.map(function(data) {
            var fixDay = data.hasOwnProperty('fixday') ? data.fixday : 1;
            var fixHour = data.hasOwnProperty('fixhour') ? data.fixhour : 1;
            return {
                timestamp: data.timestamp,
                format: data.format,
                type: data.type || null,
                fixday: fixDay,
                fixhour: fixHour
            };
        });

        var request = {
            methodname: 'core_get_user_dates',
            args: {
                contextid: Config.contextid,
                timestamps: args
            }
        };

        return Ajax.call([request], true, true)[0].then(function(results) {
            results.dates.forEach(function(value, index) {
                var date = dates[index];
                var key = getKey(date);

                addToLocalStorage(key, value);
                date.deferred.resolve(value);
            });
            return;
        })
        .catch(function(ex) {
            // If we failed to retrieve the dates then reject the date's
            // deferred objects to make sure they don't hang.
            dates.forEach(function(date) {
                date.deferred.reject(ex);
            });
        });
    };

    /**
     * Takes an array of request objects and returns a promise that
     * is resolved with an array of formatted dates.
     *
     * The values in the returned array will be ordered the same as
     * the request array.
     *
     * This function will check both the module's static promises cache
     * and the browser's session storage to see if the user dates have
     * already been loaded in order to avoid sending a network request
     * if possible.
     *
     * Only dates not found in either cache will be sent to the server
     * for transforming.
     *
     * A request object must have a timestamp key and a format key and
     * optionally may have a type key.
     *
     * E.g.
     * var request = [
     *     {
     *         timestamp: 1293876000,
     *         format: '%d %B %Y'
     *     },
     *     {
     *         timestamp: 1293876000,
     *         format: '%A, %d %B %Y, %I:%M %p',
     *         type: 'gregorian',
     *         fixday: false,
     *         fixhour: false
     *     }
     * ];
     *
     * UserDate.get(request).done(function(dates) {
     *     console.log(dates[0]); // prints "1 January 2011".
     *     console.log(dates[1]); // prints "Saturday, 1 January 2011, 10:00 AM".
     * });
     *
     * @param {array} requests
     * @return {object} jQuery promise
     */
    var get = function(requests) {
        var ajaxRequests = [];
        var promises = [];

        // Loop over each of the requested timestamp/format combos
        // and add a promise to the promises array for them.
        requests.forEach(function(request) {
            var key = getKey(request);

            // If we've already got a promise then use it.
            if (inPromisesCache(key)) {
                promises.push(getFromPromisesCache(key));
            } else {
                var deferred = $.Deferred();
                var cached = getFromLocalStorage(key);

                if (cached) {
                    // If we were able to get the value from session storage
                    // then we can resolve the deferred with that value. No
                    // need to ask the server to transform it for us.
                    deferred.resolve(cached);
                } else {
                    // Add this request to the list of ones we need to load
                    // from the server. Include the deferred so that it can
                    // be resolved when the server has responded with the
                    // transformed values.
                    request.deferred = deferred;
                    ajaxRequests.push(request);
                }

                // Remember this promise for next time so that we can
                // bail out early if it is requested again.
                addToPromisesCache(key, deferred.promise());
                promises.push(deferred.promise());
            }
        });

        // If we have any requests that we couldn't resolve from the caches
        // then let's ask the server to get them for us.
        if (ajaxRequests.length) {
            loadDatesFromServer(ajaxRequests);
        }

        // Wait for all of the promises to resolve. Some of them may be waiting
        // for a response from the server.
        return $.when.apply($, promises).then(function() {
            // This looks complicated but it's just converting an unknown
            // length of arguments into an array for the promise to resolve
            // with.
            return arguments.length === 1 ? [arguments[0]] : Array.apply(null, arguments);
        });
    };


    /**
     * For a given timestamp get the midnight value in the user's timezone.
     *
     * The calculation is performed relative to the user's midnight timestamp
     * for today to ensure that timezones are preserved.
     *
     * E.g.
     * Input:
     * timestamp: 1514836800 (01/01/2018 8pm GMT)(02/01/2018 4am GMT+8)
     * midnight: 1514851200 (02/01/2018 midnight GMT)
     * Output:
     * 1514764800 (01/01/2018 midnight GMT)
     *
     * Input:
     * timestamp: 1514836800 (01/01/2018 8pm GMT)(02/01/2018 4am GMT+8)
     * midnight: 1514822400 (02/01/2018 midnight GMT+8)
     * Output:
     * 1514822400 (02/01/2018 midnight GMT+8)
     *
     * @param {Number} timestamp The timestamp to calculate from
     * @param {Number} todayMidnight The user's midnight timestamp
     * @return {Number} The midnight value of the user's timestamp
     */
    var getUserMidnightForTimestamp = function(timestamp, todayMidnight) {
        var future = timestamp > todayMidnight;
        var diffSeconds = Math.abs(timestamp - todayMidnight);
        var diffDays = future ? Math.floor(diffSeconds / SECONDS_IN_DAY) : Math.ceil(diffSeconds / SECONDS_IN_DAY);
        var diffDaysInSeconds = diffDays * SECONDS_IN_DAY;
        // Is the timestamp in the future or past?
        var dayTimestamp = future ? todayMidnight + diffDaysInSeconds : todayMidnight - diffDaysInSeconds;
        return dayTimestamp;
    };

    return {
        get: get,
        getUserMidnightForTimestamp: getUserMidnightForTimestamp
    };
});
