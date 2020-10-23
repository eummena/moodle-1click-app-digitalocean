/*
 * Copyright (C) 2004 - 2011 John Currier
 * Copyright (C) 2016 Rafal Kasa
 * Copyright (C) 2017 Thomas Traude
 * Copyright (C) 2017 Daniel Watt
 * Copyright (C) 2017 Nils Petzaell
 *
 * This file is part of SchemaSpy.
 *
 * SchemaSpy is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SchemaSpy is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with SchemaSpy. If not, see <http://www.gnu.org/licenses/>.
 */
package org.schemaspy.input.dbms.service;

import org.schemaspy.Config;
import org.schemaspy.model.Database;
import org.schemaspy.model.Table;
import org.schemaspy.model.TableColumn;
import org.schemaspy.model.View;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.lang.invoke.MethodHandles;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLException;
import java.util.Objects;

import static org.schemaspy.input.dbms.service.ColumnLabel.*;

/**
 * Created by rkasa on 2016-12-10.
 *
 * @author John Currier
 * @author Rafal Kasa
 * @author Ismail Simsek
 * @author Thomas Traude
 * @author Daniel Watt
 * @author Nils Petzaell
 */
public class ViewService {
    private static final Logger LOGGER = LoggerFactory.getLogger(MethodHandles.lookup().lookupClass());

    private final SqlService sqlService;
    private final ColumnService columnService;

    private int deprecatedNagCounter = 0;

    public ViewService(SqlService sqlService, ColumnService columnService) {
        this.sqlService = Objects.requireNonNull(sqlService);
        this.columnService = Objects.requireNonNull(columnService);
    }

    public void gatherViewsDetails(Database database, View view) throws SQLException {
        columnService.gatherColumns(view);
        if (Objects.isNull(view.getViewDefinition())) {
            gatherViewDefinition(database, view);
        }
        database.getViewsMap().put(view.getName(), view);
    }

    /**
     * Extract the SQL that describes this view from the database
     *
     * @return
     * @throws SQLException
     */
    private void gatherViewDefinition(Database db, View view) throws SQLException {
        String selectViewSql = Config.getInstance().getDbProperties().getProperty("selectViewSql");
        if (selectViewSql == null) {
            return;
        }

        try (PreparedStatement stmt = sqlService.prepareStatement(selectViewSql, db, view.getName());
            ResultSet resultSet = stmt.executeQuery()) {
            view.setViewDefinition(getViewDefinitionFromResultSet(resultSet));
        } catch (SQLException sqlException) {
            LOGGER.error(selectViewSql);
            throw sqlException;
        }
    }

    private String getViewDefinitionFromResultSet(ResultSet resultSet) throws SQLException {
        if (isViewDefinitionColumnPresent(resultSet.getMetaData())) {
            return getFromViewDefinitionColumn(resultSet);
        }
        return getFromTextColumn(resultSet);
    }

    private boolean isViewDefinitionColumnPresent(ResultSetMetaData resultSetMetaData) throws SQLException {
        for(int i = 1; i <= resultSetMetaData.getColumnCount(); i++) {
            if ("view_definition".equalsIgnoreCase(resultSetMetaData.getColumnLabel(i))){
                return true;
            }
        }
        return false;
    }

    private String getFromViewDefinitionColumn(ResultSet resultSet) throws SQLException {
        StringBuilder viewDefinition = new StringBuilder();
        while (resultSet.next()) {
            viewDefinition.append(resultSet.getString("view_definition"));
        }
        return viewDefinition.toString();
    }

    private String getFromTextColumn(ResultSet resultSet) throws SQLException {
        StringBuilder viewDefinition = new StringBuilder();
        if (deprecatedNagCounter < 10) {
            LOGGER.warn("ColumnLabel 'text' has been deprecated and will be removed");
            deprecatedNagCounter++;
        }
        while (resultSet.next()) {
            viewDefinition.append(resultSet.getString("text"));
        }
        return viewDefinition.toString();
    }

    /**
     * Initializes view comments.
     *
     * @throws SQLException
     */
    public void gatherViewComments(Config config, Database db) {
        String sql = config.getDbProperties().getProperty("selectViewCommentsSql");
        if (sql != null) {

            try (PreparedStatement stmt = sqlService.prepareStatement(sql, db, null);
                 ResultSet rs = stmt.executeQuery()) {

                while (rs.next()) {
                    String viewName = rs.getString("view_name");
                    if (viewName == null)
                        viewName = rs.getString(TABLE_NAME);
                    Table view = db.getViewsMap().get(viewName);

                    if (view != null)
                        view.setComments(rs.getString("comments"));
                }
            } catch (SQLException sqlException) {
                // don't die just because this failed
                LOGGER.warn("Failed to retrieve view comments using SQL '{}'", sql, sqlException);
            }
        }
    }

    /**
     * Initializes view column comments.
     *
     * @throws SQLException
     */
    public void gatherViewColumnComments(Config config, Database db) {
        String sql = config.getDbProperties().getProperty("selectViewColumnCommentsSql");
        if (sql != null) {

            try (PreparedStatement stmt = sqlService.prepareStatement(sql, db, null);
                 ResultSet rs = stmt.executeQuery()) {

                while (rs.next()) {
                    String viewName = rs.getString("view_name");
                    if (viewName == null)
                        viewName = rs.getString(TABLE_NAME);
                    Table view = db.getViewsMap().get(viewName);

                    if (view != null) {
                        TableColumn column = view.getColumn(rs.getString(COLUMN_NAME));
                        if (column != null)
                            column.setComments(rs.getString(COMMENTS));
                    }
                }
            } catch (SQLException sqlException) {
                // don't die just because this failed
                LOGGER.warn("Failed to retrieve view column comments usign SQL '{}'", sql, sqlException);
            }
        }
    }
}
