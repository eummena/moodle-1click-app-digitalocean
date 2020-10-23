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
package org.schemaspy.util;

import org.junit.ClassRule;
import org.junit.Test;
import org.schemaspy.testing.AssumeClassIsPresentRule;

import static org.assertj.core.api.Assertions.assertThat;
//Only run from maven
public class ManifestUtilsIT {

    @ClassRule
    public static AssumeClassIsPresentRule rule = new AssumeClassIsPresentRule("org.apache.maven.surefire.providerapi.SurefireProvider");

    @Test
    public void getImplementationVersion() {
        assertThat(ManifestUtils.getImplementationVersion()).isEqualTo(System.getProperty("pomImplementationVersion"));
    }

    @Test
    public void getImplementationBuild() {
        assertThat(ManifestUtils.getImplementationBuild()).isEqualTo(System.getProperty("pomImplementationBuild"));
    }
}