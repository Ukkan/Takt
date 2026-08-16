Feature: Employee time tracking
  In order to record my work hours
  As an employee
  I need to clock in, clock out, and manually log time entries

  Background:
    Given I am logged in as employee

  Scenario: Employee sees their time tracking dashboard
    When I am on "/employee/time"
    Then I should see "My Time"
    And I should see "Recent entries"

  @clean_time_entries
  Scenario: Employee can clock in and then clock out
    Given I am on "/employee/time"
    And I should see "Clock in"
    When I press "Clock in"
    Then I should see "Clock out"
    When I press "Clock out"
    Then I should see "Clock in"

  @clean_time_entries
  Scenario: Employee cannot clock in twice
    Given I am on "/employee/time"
    When I press "Clock in"
    And I am on "/employee/time"
    Then I should not see "Clock in"
    And I should see "Clock out"

  @clean_time_entries
  Scenario: Employee can manually log a time entry
    Given I am on "/employee/time/log"
    When I fill in "Start Time" with "2026-06-26T09:00"
    And I fill in "End Time" with "2026-06-26T17:00"
    And I fill in "Break (minutes)" with "30"
    And I press "Save Entry"
    Then I should be on "/employee/time"

  @clean_time_entries
  Scenario: Manually logged entry overlapping an existing one is rejected
    Given I am on "/employee/time/log"
    When I fill in "Start Time" with "2026-06-26T09:00"
    And I fill in "End Time" with "2026-06-26T17:00"
    And I fill in "Break (minutes)" with "30"
    And I press "Save Entry"
    And I go to "/employee/time/log"
    And I fill in "Start Time" with "2026-06-26T16:00"
    And I fill in "End Time" with "2026-06-26T18:00"
    And I fill in "Break (minutes)" with "0"
    And I press "Save Entry"
    Then I should see "This entry overlaps with another time entry."

  @clean_time_entries
  Scenario: Manually logged entry ending before it starts is rejected
    Given I am on "/employee/time/log"
    When I fill in "Start Time" with "2026-06-26T17:00"
    And I fill in "End Time" with "2026-06-26T09:00"
    And I fill in "Break (minutes)" with "0"
    And I press "Save Entry"
    Then I should see "End time must be after start time."

  @clean_time_entries
  Scenario: Manually logged entry with a break longer than the entry is rejected
    Given I am on "/employee/time/log"
    When I fill in "Start Time" with "2026-06-26T09:00"
    And I fill in "End Time" with "2026-06-26T10:00"
    And I fill in "Break (minutes)" with "120"
    And I press "Save Entry"
    Then I should see "Break cannot be longer than the time entry itself."

  @clean_time_entries
  Scenario: An open entry is not counted towards worked time
    Given I am on "/employee/time"
    When I press "Clock in"
    And I go to "/employee/time/summary"
    Then I should see "0h 0m"

  Scenario: Employee can view their monthly summary
    When I am on "/employee/time/summary"
    Then the response status code should be 200
    And I should see "Monthly Summary"
