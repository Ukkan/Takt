<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Entity\TimeEntry;
use App\Kernel;
use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

final class FeatureContext implements Context
{
    private Kernel $kernel;
    private KernelBrowser $browser;
    private ?Crawler $crawler = null;
    private array $pendingFormValues = [];
    private array $rememberedIds = [];
    private array $rememberedTokens = [];

    #[BeforeScenario]
    public function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'behat';
        $this->kernel = new Kernel('behat', false);
        $this->kernel->boot();
        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        $this->browser = $testContainer->get('test.client');
        $this->browser->followRedirects(true);
        $this->pendingFormValues = [];
        $this->crawler = null;
    }

    #[BeforeScenario('@clean_time_entries')]
    public function cleanTimeEntries(): void
    {
        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        $em = $testContainer->get('doctrine.orm.entity_manager');
        $em->createQueryBuilder()
            ->delete(TimeEntry::class, 'te')
            ->getQuery()
            ->execute();
        $em->clear();
    }

    #[BeforeScenario('@clean_vacations')]
    public function cleanVacations(): void
    {
        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        $em = $testContainer->get('doctrine.orm.entity_manager');
        $em->createQueryBuilder()
            ->delete(Shift::class, 's')
            ->where("s.type = 'vacation'")
            ->getQuery()
            ->execute();
        $em->clear();
    }

    #[Given('I am on :path')]
    #[When('I go to :path')]
    public function iAmOn(string $path): void
    {
        $this->crawler = $this->browser->request('GET', $this->resolvePath($path));
        $this->pendingFormValues = [];
    }

    #[When('I POST to :path')]
    public function iPostTo(string $path): void
    {
        $this->crawler = $this->browser->request('POST', $this->resolvePath($path));
        $this->pendingFormValues = [];
    }

    #[When('I POST to :path with an invalid CSRF token')]
    public function iPostToWithInvalidCsrfToken(string $path): void
    {
        $this->crawler = $this->browser->request('POST', $this->resolvePath($path), ['_token' => 'invalid-token']);
        $this->pendingFormValues = [];
    }

    /**
     * Scrapes a real, working CSRF token from a rendered hidden input, rather than
     * generating one out-of-band — the CSRF token manager here is session-backed
     * and requires an active request/response cycle to persist correctly.
     */
    #[Given('I fetch a valid CSRF token for :tokenId from :path as :alias')]
    public function iFetchAValidCsrfToken(string $tokenId, string $path, string $alias): void
    {
        $resolvedTokenId = $this->resolvePath($tokenId);

        if (!preg_match('/^vacation_(approve|reject)_(\d+)$/', $resolvedTokenId, $matches)) {
            throw new RuntimeException(sprintf('Unsupported token id format "%s".', $resolvedTokenId));
        }
        [, $action, $id] = $matches;

        $crawler = $this->browser->request('GET', $this->resolvePath($path));
        $input = $crawler->filterXPath(sprintf(
            '//form[contains(@action, "/vacation/%s/%s")]//input[@name="_token"]',
            $id,
            $action,
        ));

        if ($input->count() === 0) {
            throw new RuntimeException(sprintf('Could not find a CSRF token input for "%s" on "%s".', $resolvedTokenId, $path));
        }

        $this->rememberedTokens[$alias] = $input->first()->attr('value');
    }

    #[When('I POST to :path with the remembered token :alias')]
    public function iPostToWithRememberedToken(string $path, string $alias): void
    {
        if (!array_key_exists($alias, $this->rememberedTokens)) {
            throw new RuntimeException(sprintf('No remembered CSRF token for alias "%s".', $alias));
        }

        $this->crawler = $this->browser->request('POST', $this->resolvePath($path), ['_token' => $this->rememberedTokens[$alias]]);
        $this->pendingFormValues = [];
    }

    #[Given('the vacation shift :alias is marked as :status directly in the database')]
    public function theVacationShiftIsMarkedAs(string $alias, string $status): void
    {
        if (!array_key_exists($alias, $this->rememberedIds)) {
            throw new RuntimeException(sprintf('No remembered id for alias "%s".', $alias));
        }

        $em = $this->entityManager();
        $shift = $em->find(Shift::class, $this->rememberedIds[$alias]);
        if ($shift === null) {
            throw new RuntimeException(sprintf('No shift found for remembered id "%s".', $alias));
        }

        $shift->setStatus($status);
        $em->flush();
    }

    #[When('I fill in :field with :value')]
    public function iFillIn(string $field, string $value): void
    {
        $this->pendingFormValues[$field] = $value;
    }

    #[When('I press :button')]
    public function iPress(string $button): void
    {
        if ($this->crawler === null) {
            throw new RuntimeException('No page loaded. Navigate with "I am on" first.');
        }
        $buttonNode = $this->crawler->selectButton($button);
        if ($buttonNode->count() === 0) {
            throw new RuntimeException(sprintf('Button "%s" not found on page.', $button));
        }
        $form = $buttonNode->form();
        foreach ($this->pendingFormValues as $field => $value) {
            $name = $this->resolveFieldName($field);
            $form[$name] = $value;
        }
        $this->crawler = $this->browser->submit($form);
        $this->pendingFormValues = [];
    }

    #[When('I follow :link')]
    public function iFollow(string $link): void
    {
        if ($this->crawler === null) {
            throw new RuntimeException('No page loaded. Navigate with "I am on" first.');
        }
        $linkNode = $this->crawler->selectLink($link);
        if ($linkNode->count() === 0) {
            throw new RuntimeException(sprintf('Link "%s" not found on page.', $link));
        }
        $this->crawler = $this->browser->click($linkNode->link());
    }

    #[Then('I should see :text')]
    public function iShouldSee(string $text): void
    {
        $content = (string) $this->browser->getResponse()->getContent();
        if (!str_contains($content, $text)) {
            throw new RuntimeException(sprintf('Expected to see "%s" but did not.', $text));
        }
    }

    #[Then('I should not see :text')]
    public function iShouldNotSee(string $text): void
    {
        $content = (string) $this->browser->getResponse()->getContent();
        if (str_contains($content, $text)) {
            throw new RuntimeException(sprintf('Did not expect to see "%s" but it was found.', $text));
        }
    }

    #[Then('I should be on :path')]
    public function iShouldBeOn(string $path): void
    {
        $currentPath = $this->getCurrentPath();
        if ($currentPath !== $path) {
            throw new RuntimeException(sprintf(
                'Expected to be on "%s" but was on "%s".',
                $path,
                $currentPath,
            ));
        }
    }

    #[Then('the response status code should be :code')]
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        $actual = $this->browser->getResponse()->getStatusCode();
        if ($actual !== $code) {
            throw new RuntimeException(sprintf(
                'Expected status code %d but got %d.',
                $code,
                $actual,
            ));
        }
    }

    #[Given('I am logged in as employee')]
    public function iAmLoggedInAsEmployee(): void
    {
        $this->loginAs('employee@example.com', 'password');
    }

    #[Given('I am logged in as manager')]
    public function iAmLoggedInAsManager(): void
    {
        $this->loginAs('manager@example.com', 'password');
    }

    #[Given('I am logged in as admin')]
    public function iAmLoggedInAsAdmin(): void
    {
        $this->loginAs('admin@example.com', 'password');
    }

    #[Given('I am logged in as super admin')]
    public function iAmLoggedInAsSuperAdmin(): void
    {
        $this->loginAs('admin@example.com', 'password');
    }

    #[Given('I am logged in as company admin')]
    public function iAmLoggedInAsCompanyAdmin(): void
    {
        $this->loginAs('company-admin@example.com', 'password');
    }

    #[Given('I am logged in as manager of the second company')]
    public function iAmLoggedInAsManagerB(): void
    {
        $this->loginAs('manager-b@example.com', 'password');
    }

    #[Given('I am logged in as employee of the second company')]
    public function iAmLoggedInAsEmployeeB(): void
    {
        $this->loginAs('employee-b@example.com', 'password');
    }

    #[Given('I remember the id of the employee with email :email as :alias')]
    public function iRememberTheEmployeeIdFor(string $email, string $alias): void
    {
        $employee = $this->entityManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(Employee::class, 'e')
            ->join('e.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        if ($employee === null) {
            throw new RuntimeException(sprintf('No employee found for email "%s".', $email));
        }

        $this->rememberedIds[$alias] = $employee->getId();
    }

    /**
     * Creates a fresh TimeEntry directly via the EM, independent of whatever
     * fixture/demo data currently exists — other features' @clean_time_entries
     * tags wipe the whole table, so isolation scenarios must not depend on
     * fixture-seeded entries still being present.
     */
    #[Given('I create a time entry for the employee with email :email, remembered as :alias')]
    public function iCreateATimeEntryFor(string $email, string $alias): void
    {
        $em = $this->entityManager();
        $employee = $em->createQueryBuilder()
            ->select('e')
            ->from(Employee::class, 'e')
            ->join('e.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        if ($employee === null) {
            throw new RuntimeException(sprintf('No employee found for email "%s".', $email));
        }

        $entry = new TimeEntry($employee->getCompany(), $employee, new \DateTime('yesterday 08:00'));
        $entry->setEndTime(new \DateTime('yesterday 16:00'));
        $entry->setSource('manual');
        $em->persist($entry);
        $em->flush();

        $this->rememberedIds[$alias] = $entry->getId();
    }

    /**
     * Creates a fresh approved vacation Shift directly via the EM, independent
     * of fixture/demo data (see iCreateATimeEntryFor for why).
     */
    #[Given('I create an approved vacation shift for the employee with email :email, remembered as :alias')]
    public function iCreateAnApprovedVacationShiftFor(string $email, string $alias): void
    {
        $em = $this->entityManager();
        $employee = $em->createQueryBuilder()
            ->select('e')
            ->from(Employee::class, 'e')
            ->join('e.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        if ($employee === null) {
            throw new RuntimeException(sprintf('No employee found for email "%s".', $email));
        }

        $shift = new Shift($employee->getCompany(), $employee, new \DateTimeImmutable('+30 days'));
        $shift->setEndTime(new \DateTimeImmutable('+31 days'));
        $shift->setType('vacation');
        $shift->setStatus('approved');
        $em->persist($shift);
        $em->flush();

        $this->rememberedIds[$alias] = $shift->getId();
    }

    /**
     * Creates a fresh pending vacation Shift with a recognizable note, so CSRF
     * scenarios don't depend on the fixture-seeded pending request surviving
     * earlier scenarios (a full-suite re-run without reloading fixtures used
     * to fail because another scenario consumed the fixture's pending request).
     */
    #[Given('I create a pending vacation shift with note :note for the employee with email :email, remembered as :alias')]
    public function iCreateAPendingVacationShiftFor(string $note, string $email, string $alias): void
    {
        $em = $this->entityManager();
        $employee = $em->createQueryBuilder()
            ->select('e')
            ->from(Employee::class, 'e')
            ->join('e.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        if ($employee === null) {
            throw new RuntimeException(sprintf('No employee found for email "%s".', $email));
        }

        $shift = new Shift($employee->getCompany(), $employee, new \DateTimeImmutable('+60 days'));
        $shift->setEndTime(new \DateTimeImmutable('+61 days'));
        $shift->setType('vacation');
        $shift->setStatus('pending');
        $shift->setNote($note);
        $em->persist($shift);
        $em->flush();

        $this->rememberedIds[$alias] = $shift->getId();
    }

    #[Given('I remember the id of the first time entry for the employee with email :email as :alias')]
    public function iRememberTheFirstTimeEntryIdFor(string $email, string $alias): void
    {
        $entry = $this->entityManager()
            ->createQueryBuilder()
            ->select('t')
            ->from(TimeEntry::class, 't')
            ->join('t.employee', 'e')
            ->join('e.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($entry === null) {
            throw new RuntimeException(sprintf('No time entry found for employee email "%s".', $email));
        }

        $this->rememberedIds[$alias] = $entry->getId();
    }

    #[Given('I remember the id of the first vacation shift for the employee with email :email as :alias')]
    public function iRememberTheFirstVacationShiftIdFor(string $email, string $alias): void
    {
        $shift = $this->entityManager()
            ->createQueryBuilder()
            ->select('s')
            ->from(Shift::class, 's')
            ->join('s.employee', 'e')
            ->join('e.user', 'u')
            ->where('u.email = :email')
            ->andWhere("s.type = 'vacation'")
            ->setParameter('email', $email)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($shift === null) {
            throw new RuntimeException(sprintf('No vacation shift found for employee email "%s".', $email));
        }

        $this->rememberedIds[$alias] = $shift->getId();
    }

    #[Given('I remember the id of the pending vacation shift for the employee with email :email as :alias')]
    public function iRememberThePendingVacationShiftIdFor(string $email, string $alias): void
    {
        $shift = $this->entityManager()
            ->createQueryBuilder()
            ->select('s')
            ->from(Shift::class, 's')
            ->join('s.employee', 'e')
            ->join('e.user', 'u')
            ->where('u.email = :email')
            ->andWhere("s.type = 'vacation'")
            ->andWhere("s.status = 'pending'")
            ->setParameter('email', $email)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($shift === null) {
            throw new RuntimeException(sprintf('No pending vacation shift found for employee email "%s".', $email));
        }

        $this->rememberedIds[$alias] = $shift->getId();
    }

    private function entityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->kernel->getContainer()->get('test.service_container')->get('doctrine.orm.entity_manager');
    }

    private function resolvePath(string $path): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches): string {
            $alias = $matches[1];
            if (!array_key_exists($alias, $this->rememberedIds)) {
                throw new RuntimeException(sprintf('No remembered id for alias "%s".', $alias));
            }
            return (string) $this->rememberedIds[$alias];
        }, $path);
    }

    private function loginAs(string $email, string $password): void
    {
        $this->iAmOn('/login');
        $this->pendingFormValues = ['email' => $email, 'password' => $password];
        $this->iPress('Sign in');
    }

    private function getCurrentPath(): string
    {
        return parse_url(
            $this->browser->getRequest()->getUri(),
            PHP_URL_PATH,
        ) ?? '/';
    }

    private function resolveFieldName(string $field): string
    {
        if ($this->crawler === null) {
            return $field;
        }

        // Try exact name attribute
        $byName = $this->crawler->filterXPath("//*[@name=" . json_encode($field) . "]");
        if ($byName->count() > 0) {
            return $field;
        }

        // Try by id attribute
        $byId = $this->crawler->filterXPath("//*[@id=" . json_encode($field) . "]");
        if ($byId->count() > 0) {
            return $byId->first()->attr('name') ?? $field;
        }

        // Try by label text → for attribute → input name
        $label = $this->crawler->filterXPath(
            '//label[normalize-space(string(.))=' . json_encode($field) . ']'
        );
        if ($label->count() > 0) {
            $forAttr = $label->first()->attr('for');
            if ($forAttr !== null) {
                $input = $this->crawler->filterXPath("//*[@id=" . json_encode($forAttr) . "]");
                if ($input->count() > 0) {
                    return $input->first()->attr('name') ?? $field;
                }
            }
        }

        return $field;
    }
}
