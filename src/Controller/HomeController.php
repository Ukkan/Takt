<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public marketing page. The hero calendar is pre-rendered server-side;
 * clock, timer, calendar hover and the signup modal are enhanced in
 * public/js/landing.js.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'calendar' => $this->calendar(2026, 5),
        ]);
    }

    /**
     * Signup stub for the landing modal: validates and echoes back.
     * Persistence + magic-link mail are intentionally not implemented yet.
     */
    #[Route('/signup', name: 'app_landing_signup', methods: ['POST'])]
    public function signup(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('signup', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'errors' => ['form' => 'Invalid security token, reload the page.']], 422);
        }

        $email   = trim((string) $request->request->get('email', ''));
        $company = trim((string) $request->request->get('company', ''));
        $size    = (string) $request->request->get('size', '11–50');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid work email.';
        }
        if (mb_strlen($company) < 2) {
            $errors['company'] = 'Company name is required.';
        }

        if ($errors) {
            return $this->json(['ok' => false, 'errors' => $errors], 422);
        }

        // TODO: persist pending workspace + send magic link

        return $this->json([
            'ok'      => true,
            'email'   => $email,
            'company' => $company,
            'size'    => $size,
        ]);
    }

    /**
     * Calendar payload for the hero. Demo month is pinned to May 2026 to
     * match the marketing copy; events would normally come from Shift data.
     *
     * @return array{
     *   month: string, year: int, today: int, firstDow: int, daysInMonth: int,
     *   events: array<int, array{kind: string, label: string, who: string}>,
     *   eventCount: int, peopleOut: int
     * }
     */
    private function calendar(int $year, int $month): array
    {
        $first       = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $firstDow    = (int) $first->format('w'); // 0=Sun … 6=Sat
        $daysInMonth = (int) $first->format('t');

        $events = [
            1  => ['kind' => 'public', 'label' => 'Święto Pracy',              'who' => 'Poland · Office closed'],
            3  => ['kind' => 'public', 'label' => 'Święto Konstytucji 3 Maja', 'who' => 'Poland · Office closed'],
            8  => ['kind' => 'pto',    'label' => 'Aleksandra · urlop',        'who' => 'Aleksandra Nowak — Engineering'],
            11 => ['kind' => 'pto',    'label' => 'Jonah · sick',              'who' => 'Jonah Reeves — Design'],
            18 => ['kind' => 'pto',    'label' => 'Priya · vacation',          'who' => 'Priya Shah — Finance'],
            19 => ['kind' => 'pto',    'label' => 'Priya · vacation',          'who' => 'Priya Shah — Finance'],
            20 => ['kind' => 'pto',    'label' => 'Priya · vacation',          'who' => 'Priya Shah — Finance'],
            29 => ['kind' => 'pto',    'label' => 'Kacper · personal',         'who' => 'Kacper Wiśniewski — Sales'],
        ];

        $today = (new \DateTimeImmutable('today'))->format('Y-n') === sprintf('%d-%d', $year, $month)
            ? (int) (new \DateTimeImmutable('today'))->format('j')
            : 13; // fixed highlight for the demo month

        $peopleOut = count(array_unique(array_column(
            array_filter($events, fn(array $e) => $e['kind'] === 'pto'),
            'who'
        )));

        return [
            'month'       => $first->format('F'),
            'year'        => $year,
            'today'       => $today,
            'firstDow'    => $firstDow,
            'daysInMonth' => $daysInMonth,
            'events'      => $events,
            'eventCount'  => count($events),
            'peopleOut'   => $peopleOut,
        ];
    }
}
