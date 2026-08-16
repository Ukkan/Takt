Feature: Manager team time management
  In order to manage my team's time records
  As a manager
  I need to view employees, their entries, and add or edit time records

  Background:
    Given I am logged in as manager

  Scenario: Manager sees the team dashboard with employee list
    When I am on "/manager"
    Then I should see "Team Dashboard"
    And I should see "Employee User"

  Scenario: Manager can view an employee's time entries
    Given I am on "/manager"
    When I follow "View time"
    Then I should see "Employee User"
    And I should see "Add entry"

  @clean_time_entries
  Scenario: Manager can add a time entry for an employee
    Given I am on "/manager"
    When I follow "View time"
    And I follow "Add entry"
    And I fill in "Start Time" with "2026-06-26T08:00"
    And I fill in "End Time" with "2026-06-26T16:00"
    And I fill in "Break (minutes)" with "60"
    And I press "Save Entry"
    Then I should see "Employee User"

  Scenario: Manager can view pending vacation requests
    When I am on "/manager/vacations"
    Then the response status code should be 200

  Scenario: Approving a vacation request with an invalid CSRF token does not approve it
    Given I create a pending vacation shift with note "CSRF-negative-test" for the employee with email "employee@example.com", remembered as "pendingVacation"
    When I POST to "/manager/vacation/{pendingVacation}/approve" with an invalid CSRF token
    When I go to "/manager/vacations"
    Then I should see "CSRF-negative-test"

  Scenario: Approving an already-processed vacation request is rejected even with a valid token
    Given I create a pending vacation shift with note "CSRF-stale-test" for the employee with email "employee@example.com", remembered as "staleVacation"
    And I fetch a valid CSRF token for "vacation_approve_{staleVacation}" from "/manager/vacations" as "staleToken"
    And the vacation shift "staleVacation" is marked as "approved" directly in the database
    When I POST to "/manager/vacation/{staleVacation}/approve" with the remembered token "staleToken"
    Then the response status code should be 404
