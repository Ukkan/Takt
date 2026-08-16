Feature: Authentication and authorisation
  In order to access Takt
  As a user
  I need to be able to sign in with valid credentials and be restricted to my role's area

  Scenario: Employee is redirected to their dashboard after login
    Given I am on "/login"
    When I fill in "email" with "employee@example.com"
    And I fill in "password" with "password"
    And I press "Sign in"
    Then I should be on "/employee/time"

  Scenario: Manager is redirected to manager dashboard after login
    Given I am on "/login"
    When I fill in "email" with "manager@example.com"
    And I fill in "password" with "password"
    And I press "Sign in"
    Then I should be on "/manager"

  Scenario: Admin is redirected to admin stats after login
    Given I am on "/login"
    When I fill in "email" with "admin@example.com"
    And I fill in "password" with "password"
    And I press "Sign in"
    Then I should be on "/admin-stats"

  Scenario: Invalid credentials show an error message
    Given I am on "/login"
    When I fill in "email" with "employee@example.com"
    And I fill in "password" with "wrongpassword"
    And I press "Sign in"
    Then I should be on "/login"
    And I should see "Invalid credentials"

  Scenario: Unauthenticated user is redirected to login
    When I go to "/employee/time"
    Then I should be on "/login"

  Scenario: Employee cannot access manager area
    Given I am logged in as employee
    When I go to "/manager"
    Then the response status code should be 403

  Scenario: Employee cannot access admin area
    Given I am logged in as employee
    When I go to "/admin-stats"
    Then the response status code should be 403

  Scenario: Manager cannot access admin area
    Given I am logged in as manager
    When I go to "/admin-stats"
    Then the response status code should be 403
