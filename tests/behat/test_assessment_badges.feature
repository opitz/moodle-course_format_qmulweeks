@format @format_qmulweeks
Feature: See various assessment badges
  As a student
  In order to start a quiz with confidence
  I need to see a badge if there is a time limit and a badge for an attempt

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher  | Teacher   | One      | teacher@example.com |
      | student  | Student   | One      | student@example.com  |
    And the following "courses" exist:
      | fullname | shortname | format     | coursedisplay | numsections |
      | Course 1 | C1        | qmulweeks | 0             | 5           |
    And the following "course enrolments" exist:
      | user     | course | role            |
      | teacher  | C1     | editingteacher  |
      | student  | C1     | student         |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext               |
      | Test questions   | truefalse   | TF1   | Text of the first question |
      | Test questions   | truefalse   | TF2   | Second question |

  @javascript
  Scenario: As a student see a badge with a time limit and a badge with no attempt
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | timeclose  | section |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    | 1767139200 | 1       |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |
      | TF2      | 1    | 3.0     |
    When I log in as "student"
    And I am on "Course 1" course homepage
    Then I should see "Due 31 December 2025"
    And I should see "Not attempted"

  @javascript
  Scenario: As a teacher see a badge with a time limit and a badge with no attempt
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | timeclose  | section |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    | 1767139200 | 1       |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |
      | TF2      | 1    | 3.0     |
    When I log in as "teacher"
    And I am on "Course 1" course homepage
    Then I should see "Due 31 December 2025"
#    And I should see "0 of 1 Attempted"

  @javascript
  Scenario: As a student after having attempted a quiz I should see a badge telling me so
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | timeclose  | section |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    | 1767139200 | 1       |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |
      | TF2      | 1    | 3.0     |
    And user "student" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | True     |
      |   2  | False    |
    When I log in as "student"
    And I am on "Course 1" course homepage
    Then I should see "Due 31 December 2025"
    And I should see "Finished"

  @javascript
  Scenario: As a teacher I should see no due date badge when no due date is set
    Given the following "activities" exist:
      | activity  | course  | idnumber  | name              | intro             |
      | assign    | C1      | assign1   | Test Assignment 1 | Test Assignment 1 |
    When I log in as "teacher"
    And I am on "Course 1" course homepage
    Then I should not see "Was due"
