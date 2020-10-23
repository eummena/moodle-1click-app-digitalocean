@core @core_question @javascript
Feature: A teacher can put questions in categories in the question bank
  In order to organize my questions
  As a teacher
  I create and edit categories and move questions between them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | weeks |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | questioncategory | name           |
      | Course       | C1        | Top              | top            |
      | Course       | C1        | top              | Default for C1 |
      | Course       | C1        | Default for C1   | Subcategory    |
      | Course       | C1        | top              | Used category  |
    And the following "questions" exist:
      | questioncategory | qtype | name                      | questiontext                  |
      | Used category    | essay | Test question to be moved | Write about whatever you want |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  Scenario: A new question category can be created
    When I navigate to "Question bank > Categories" in current page administration
    And I set the following fields to these values:
      | Name            | New Category 1    |
      | Parent category | Top               |
      | Category info   | Created as a test |
      | ID number       | newcatidnumber    |
    And I press "submitbutton"
    Then I should see "New Category 1 ID number newcatidnumber (0)"
    And I should see "Created as a test" in the "New Category 1" "list_item"
    And "New Category 1 [newcatidnumber]" "option" should exist in the "Parent category" "select"

  Scenario: A question category can be edited
    When I navigate to "Question bank > Categories" in current page administration
    And I click on "Edit this category" "link" in the "Subcategory" "list_item"
    And the field "parent" matches value "&nbsp;&nbsp;&nbsp;Default for C1"
    And I set the following fields to these values:
      | Name            | New name     |
      | Category info   | I was edited |
    And I press "Save changes"
    Then I should see "New name"
    And I should see "I was edited" in the "New name" "list_item"

  Scenario: An empty question category can be deleted
    When I navigate to "Question bank > Categories" in current page administration
    And I click on "Delete" "link" in the "Subcategory" "list_item"
    Then I should not see "Subcategory"

  Scenario: An non-empty question category can be deleted if you move the contents elsewhere
    When I navigate to "Question bank > Categories" in current page administration
    And I click on "Delete" "link" in the "Used category" "list_item"
    And I should see "The category 'Used category' contains 1 questions"
    And I press "Save in category"
    Then I should not see "Used category"
    And I should see "Default for C1 (1)"

  @javascript
  Scenario: Move a question between categories via the question page
    When I navigate to "Question bank > Questions" in current page administration
    And I set the field "Select a category" to "Used category"
    And I click on "Test question to be moved" "checkbox" in the "Test question to be moved" "table_row"
    And I set the field "Question category" to "Subcategory"
    And I press "Move to >>"
    Then I should see "Test question to be moved"
    And the field "Select a category" matches value "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Subcategory (1)"
    And the "Select a category" select box should contain "Used category"
    And the "Select a category" select box should not contain "Used category (1)"

  @javascript
  Scenario: Move a question between categories via the question settings page
    When I navigate to "Question bank > Questions" in current page administration
    And I set the field "Select a category" to "Used category"
    And I choose "Edit question" action for "Test question to be moved" in the question bank
    And I click on "Use this category" "checkbox"
    And I set the field "Save in category" to "Subcategory"
    And I press "id_submitbutton"
    Then I should see "Test question to be moved"
    And the field "Select a category" matches value "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Subcategory (1)"
    And the "Select a category" select box should contain "Used category"
    And the "Select a category" select box should not contain "Used category (1)"
