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
package org.schemaspy.testing;

import junit.framework.AssertionFailedError;
import org.springframework.boot.test.rule.OutputCapture;

import java.lang.reflect.Field;
import java.util.List;

/**
 * @author Nils Petzaell
 */
public class ResettingOutputCapture extends OutputCapture {

    @Override
    protected void releaseOutput() {
        super.releaseOutput();
        try {
            clearMatchers();
        } catch (NoSuchFieldException | IllegalAccessException e) {
            throw new AssertionFailedError("Failed to clear 'matchers'");
        }
    }

    private void clearMatchers() throws NoSuchFieldException, IllegalAccessException {
        Field matchersField = OutputCapture.class.getDeclaredField("matchers");
        try {
            matchersField.setAccessible(true);
            List matchers = (List) matchersField.get(this);
            matchers.clear();
        } finally {
            matchersField.setAccessible(false);
        }
    }
}
