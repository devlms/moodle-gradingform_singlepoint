@gradingform @gradingform_singlepoint @mod_forum @javascript
Feature: Single-point rubric in whole forum grading
  In order to grade forum participation against one standard per criterion
  As a teacher
  I need the single-point rubric in the forum grader

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
    And the following "activities" exist:
      | activity | name       | intro | course |
      | forum    | Discussion | Talk  | C1     |

  Scenario: A teacher grades a forum with the single-point rubric
    Given I change window size to "large"
    And I am on the "Discussion" "forum activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the following fields to these values:
      | Whole forum grading > Type | Point               |
      | Grading method             | Single-point rubric |
    And I press "Save and display"
    And I go to "Discussion" advanced grading definition page
    And I set the following fields to these values:
      | Name               | Participation        |
      | critname[0]        | Reply                |
      | critdescription[0] | Answers a classmate  |
      | critpoints[0]      | 10                   |
    And I press "Add a criterion"
    And I set the following fields to these values:
      | critname[1]        | Source               |
      | critdescription[1] | Cites a reading      |
      | critpoints[1]      | 10                   |
    And I press "Save rubric and make it ready"
    When I am on the "Discussion" "forum activity" page
    And I press "Grade users"
    And I click on "Reply: Beyond" "radio"
    And I click on "Source: Not yet" "radio"
    And I set the field "Comment on Source" to "Cite one of this week's readings"
    And I press "Save"
    And I am on the "Course 1" "grades > Grader report > View" page
    Then I should see "50.00" in the "student1@example.com" "table_row"
