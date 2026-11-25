@gradingform @gradingform_singlepoint
Feature: Single-point rubric
  In order to give feedback against one clear standard per criterion
  As a teacher
  I need to define a single-point rubric and grade with it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity                            | assign      |
      | course                              | C1          |
      | name                                | Essay       |
      | assignsubmission_onlinetext_enabled | 1           |
      | advancedgradingmethod_submissions   | singlepoint |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I go to "Essay" advanced grading definition page
    And I set the following fields to these values:
      | Name               | Essay rubric                |
      | critname[0]        | Thesis                      |
      | critdescription[0] | States a clear, arguable claim |
      | critpoints[0]      | 10                          |
    And I press "Add a criterion"
    And I set the following fields to these values:
      | critname[1]        | Evidence                    |
      | critdescription[1] | Every claim has a source    |
      | critpoints[1]      | 10                          |
    And I press "Save rubric and make it ready"

  Scenario: A teacher defines a single-point rubric without JavaScript
    Then I should see "Ready for use"
    And I should see "Thesis"
    And I should see "States a clear, arguable claim"
    And I should see "Every claim has a source"
    And I should see "Meets and Beyond earn the criterion's points. Not yet earns 0%."

  Scenario: The editor refuses a criterion without points
    When I go to "Essay" advanced grading definition page
    And I set the field "critpoints[1]" to "0"
    And I press "Save rubric and make it ready"
    Then I should see "Enter points greater than 0."

  @javascript
  Scenario: A teacher grades with the rubric and the student sees the feedback
    When I go to "Student 1" "Essay" activity advanced grading page
    And I click on "Thesis: Meets" "radio"
    And I click on "Evidence: Not yet" "radio"
    And I set the field "Comment on Evidence" to "Cite your sources"
    And I press "Save changes"
    And I am on the "Essay" "assign activity" page
    And I navigate to "Submissions" in current page administration
    Then I should see "50.00" in the "Student 1" "table_row"
    And I log out
    And I am on the "Essay" "assign activity" page logged in as student1
    And I should see "50.00" in the ".feedback" "css_element"
    And I should see "Not yet" in the ".feedback" "css_element"
    And I should see "Cite your sources" in the ".feedback" "css_element"
