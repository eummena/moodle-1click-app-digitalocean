@qtype @qtype_ddmarker
Feature: Test editing a drag and drop markers questions
  As a teacher
  In order to be able to update my drag and drop markers questions
  I need to edit them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | T1        | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype    | name         | template |
      | Test questions   | ddmarker | Drag markers | mkmap    |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

  @javascript
  Scenario: Edit a drag and drop markers question
    When I choose "Edit question" action for "Drag markers" in the question bank
    And I set the following fields to these values:
      | Question name | Edited question name |
    And I press "id_submitbutton"
    Then I should see "Edited question name"
