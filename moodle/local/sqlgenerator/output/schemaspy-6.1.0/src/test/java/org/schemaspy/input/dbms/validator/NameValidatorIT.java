/*
 * Copyright (C) 2018 Nils Petzaell
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
package org.schemaspy.input.dbms.validator;

import org.junit.Test;
import org.schemaspy.Config;
import org.schemaspy.validator.NameValidator;

import static org.assertj.core.api.Assertions.assertThat;

/**
 * @author Nils Petzaell
 */
public class NameValidatorIT {

    @Test
    public void defaultExcludesDollarSign() {
        Config config = new Config();
        NameValidator nameValidator = new NameValidator("table", config.getTableInclusions(), config.getTableExclusions(), new String[]{"TABLE"});
        boolean valid = nameValidator.isValid("abc$123", "TABLE");
        assertThat(valid).isFalse();
    }

    @Test
    public void overrideDefaultIncludesDollarSign() {
        Config config = new Config("-I", "");
        NameValidator nameValidator = new NameValidator("table", config.getTableInclusions(), config.getTableExclusions(), new String[]{"TABLE"});
        boolean valid = nameValidator.isValid("abc$123", "TABLE");
        assertThat(valid).isTrue();
    }
}
