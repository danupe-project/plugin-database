<?php

namespace Danupe\Plugin\Database\Tests;

use PHPUnit\Framework\TestCase;
use Danupe\Plugin\Database\Classes\MigrationsManager;
use Danupe\Plugin\Database\Classes\Database;

class MigrationsManagerTest extends TestCase
{
    private MigrationsManager $manager;
    private Database $db;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->manager = new MigrationsManager($this->db);
    }

    public function testMigrateMethod(): void
    {
        $this->db->expects($this->once())
            ->method('table')
            ->with('migrations')
            ->willReturnSelf();

        $this->db->expects($this->once())
            ->method('create')
            ->with($this->anything())
            ->willReturn(true);

        $this->manager->migrate();
        $this->expectOutputString("Migrated: test_migration.php\n");
    }

    public function testRollbackMethod(): void
    {
        $this->db->expects($this->once())
            ->method('table')
            ->with('migrations')
            ->willReturnSelf();

        $this->db->expects($this->once())
            ->method('delete')
            ->with(['migration' => 'test_migration.php'])
            ->willReturn(true);

        $this->manager->rollback();
        $this->expectOutputString("Rolled back: test_migration.php\n");
    }
}
