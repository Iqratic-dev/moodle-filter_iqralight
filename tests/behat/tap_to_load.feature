@filter @filter_iqralight
Feature: Heavy media wait for the learner's tap
  In order to save mobile data
  As a learner with a weak connection
  I need embedded players to load only when I ask for them

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Amina     | Learner  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | content                                                                |
      | page     | Lesson | C1     | <p>Watch:</p><iframe src="https://www.youtube.com/embed/abc"></iframe> |
    And the "iqralight" filter is "on"

  Scenario: An embedded video is replaced by a load button and a direct link
    When I am on the "Lesson" "page activity" page logged in as student1
    Then I should see "Load the video"
    And I should see "Open directly"
