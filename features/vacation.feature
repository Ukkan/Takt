Feature: Employee vacation requests
  In order to plan my time off
  As an employee
  I need to submit vacation requests and view their status

  Background:
    Given I am logged in as employee

  Scenario: Employee can view their vacation requests page
    When I am on "/employee/vacation"
    Then the response status code should be 200
    And I should see "Vacation"

  @clean_vacations
  Scenario: Employee can submit a vacation request
    Given I am on "/employee/vacation/request"
    When I fill in "Start Date" with "2026-07-01"
    And I fill in "End Date" with "2026-07-05"
    And I press "Submit Request"
    Then I should be on "/employee/vacation"

  @clean_vacations
  Scenario: Overlapping vacation request is rejected
    Given I am on "/employee/vacation/request"
    When I fill in "Start Date" with "2026-07-01"
    And I fill in "End Date" with "2026-07-05"
    And I press "Submit Request"
    And I go to "/employee/vacation/request"
    And I fill in "Start Date" with "2026-07-03"
    And I fill in "End Date" with "2026-07-08"
    And I press "Submit Request"
    Then I should see "This period overlaps with a vacation request you already submitted."

  Scenario: Vacation request with end date before start date is rejected
    Given I am on "/employee/vacation/request"
    When I fill in "Start Date" with "2026-08-10"
    And I fill in "End Date" with "2026-08-05"
    And I press "Submit Request"
    Then I should see "End date cannot be before the start date."
