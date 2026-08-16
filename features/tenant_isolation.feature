Feature: Cross-company data isolation
  In order to guarantee that companies cannot see or modify each other's data
  As the system
  I need to reject any attempt to access another company's employees, time entries, or vacation requests

  Background:
    Given I remember the id of the employee with email "employee@example.com" as "employeeA"
    And I remember the id of the employee with email "employee-b@example.com" as "employeeB"
    And I create a time entry for the employee with email "employee@example.com", remembered as "entryA"
    And I create a time entry for the employee with email "employee-b@example.com", remembered as "entryB"
    And I create an approved vacation shift for the employee with email "employee-b@example.com", remembered as "vacationB"

  Scenario: Manager cannot view an employee from another company
    Given I am logged in as manager
    When I go to "/manager/employee/{employeeB}"
    Then the response status code should be 403

  Scenario: Manager cannot add a time entry for an employee from another company
    Given I am logged in as manager
    When I POST to "/manager/employee/{employeeB}/time/add"
    Then the response status code should be 403

  Scenario: Manager cannot edit a time entry belonging to another company
    Given I am logged in as manager
    When I POST to "/manager/time/{entryB}/edit"
    Then the response status code should be 403

  Scenario: Manager cannot delete a time entry belonging to another company
    Given I am logged in as manager
    When I POST to "/manager/time/{entryB}/delete"
    Then the response status code should be 403

  Scenario: Manager cannot approve a vacation request belonging to another company
    Given I am logged in as manager
    When I POST to "/manager/vacation/{vacationB}/approve"
    Then the response status code should be 403

  Scenario: Manager cannot reject a vacation request belonging to another company
    Given I am logged in as manager
    When I POST to "/manager/vacation/{vacationB}/reject"
    Then the response status code should be 403

  Scenario: Manager of the second company cannot view an employee from the first company
    Given I am logged in as manager of the second company
    When I go to "/manager/employee/{employeeA}"
    Then the response status code should be 403

  Scenario: Company admin is restricted the same way a manager is
    Given I am logged in as company admin
    When I go to "/manager/employee/{employeeB}"
    Then the response status code should be 403

  Scenario: Employee cannot delete their own-company colleague's or another company's time entry
    Given I am logged in as employee of the second company
    When I POST to "/employee/time/{entryA}/delete"
    Then the response status code should be 403

  Scenario: Company admin's system overview is scoped to their own company
    Given I am logged in as company admin
    When I go to "/admin-stats"
    Then I should see "Scoped to Demo Company."

  Scenario: Super admin's system overview spans all companies
    Given I am logged in as super admin
    When I go to "/admin-stats"
    Then I should see "Platform-wide view (all companies)."
