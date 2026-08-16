<?php

namespace App\Tests\Entity;

use App\Entity\Company;
use App\Entity\Employee;
use App\Entity\TimeEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class TimeEntryTest extends TestCase
{
    private function makeEntry(string $start, ?string $end, int $breakMinutes = 0): TimeEntry
    {
        $company = new Company();
        $employee = new Employee($company);

        $entry = new TimeEntry($company, $employee, new \DateTime($start));
        if ($end !== null) {
            $entry->setEndTime(new \DateTime($end));
        }
        $entry->setBreakMinutes($breakMinutes);

        return $entry;
    }

    public function testDurationIsNullForAnActiveEntry(): void
    {
        $entry = $this->makeEntry('2026-06-01 08:00', null);

        $this->assertNull($entry->getDurationMinutes());
        $this->assertTrue($entry->isActive());
    }

    public function testDurationSubtractsBreakFromElapsedTime(): void
    {
        $entry = $this->makeEntry('2026-06-01 08:00', '2026-06-01 16:00', 60);

        $this->assertSame(420, $entry->getDurationMinutes()); // 8h - 60min break
        $this->assertFalse($entry->isActive());
    }

    public function testValidateDurationIsNoOpForAnActiveEntry(): void
    {
        $entry = $this->makeEntry('2026-06-01 08:00', null);
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $entry->validateDuration($context);
    }

    public function testValidateDurationRejectsEndTimeBeforeStartTime(): void
    {
        $entry = $this->makeEntry('2026-06-01 16:00', '2026-06-01 08:00');

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->with('endTime')->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('End time must be after start time.')
            ->willReturn($builder);

        $entry->validateDuration($context);
    }

    public function testValidateDurationRejectsEndTimeEqualToStartTime(): void
    {
        $entry = $this->makeEntry('2026-06-01 08:00', '2026-06-01 08:00');

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('buildViolation')->willReturn($builder);

        $entry->validateDuration($context);
    }

    public function testValidateDurationRejectsBreakLongerThanRawDuration(): void
    {
        $entry = $this->makeEntry('2026-06-01 08:00', '2026-06-01 09:00', 90); // 60 min entry, 90 min break

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->with('breakMinutes')->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('Break cannot be longer than the time entry itself.')
            ->willReturn($builder);

        $entry->validateDuration($context);
    }

    public function testValidateDurationAcceptsAValidEntry(): void
    {
        $entry = $this->makeEntry('2026-06-01 08:00', '2026-06-01 16:00', 60);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $entry->validateDuration($context);
    }
}
