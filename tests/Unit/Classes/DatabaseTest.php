<?php

namespace Danupe\Plugin\Database\Tests\Unit\Classes;

use Danupe\Core\TestCase;
use Danupe\Plugin\Database\Classes\Database;
use PDO;

class DatabaseTest extends TestCase
{
    private Database $database;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $type = danupe()->config()->get('plugin-database.type', 'mysql');
        $config = danupe()->config()->get("plugin-database.$type");
        $this->pdo = new PDO("mysql:host=" . $config['host'] . ";dbname=" . $config['database'] . ";charset=utf8mb4", $config['username'], $config['password']);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        )');

        $this->database = new Database();
        dd($this->database);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $type = danupe()->config()->get('plugin-database.type', 'mysql');
        $config = danupe()->config()->get("plugin-database.$type");
        $pdo = new PDO("mysql:host=" . $config['host'] . ";dbname=" . $config['database'] . ";charset=utf8mb4", $config['username'], $config['password']);
        $pdo->exec('DROP TABLE IF EXISTS users');
    }

    public function testTableSetsTableName(): void
    {
        $result = $this->database->table('users');
        $this->assertInstanceOf(Database::class, $result);
    }

    public function testAllReturnsData(): void
    {
        $this->database->table('users');
        $this->database->create(['name' => 'Test User']);
        $result = $this->database->all();
        $this->assertCount(1, $result);
        $this->assertSame('Test User', $result[0]['name']);
    }

    public function testGetReturnsFilteredData(): void
    {
        $this->database->table('users');
        $this->database->create(['name' => 'Filtered User']);
        $result = $this->database->get(['name' => 'Filtered User']);
        $this->assertSame([['id' => 1, 'name' => 'Filtered User']], $result);
    }

    public function testCreateInsertsData(): void
    {
        $this->database->table('users');
        $result = $this->database->create(['name' => 'New User']);
        $this->assertTrue($result);

        $data = $this->database->get(['name' => 'New User']);
        $this->assertCount(1, $data);
        $this->assertSame('New User', $data[0]['name']);
    }

    public function testUpdateModifiesData(): void
    {
        $this->database->table('users');
        $this->database->create(['name' => 'Old Name']);
        $result = $this->database->update(['name' => 'Updated User'], ['name' => 'Old Name']);
        $this->assertTrue($result);

        $data = $this->database->get(['name' => 'Updated User']);
        $this->assertCount(1, $data);
        $this->assertSame('Updated User', $data[0]['name']);
    }

    public function testDeleteRemovesData(): void
    {
        $this->database->table('users');
        $this->database->create(['name' => 'User to Delete']);
        $result = $this->database->delete(['name' => 'User to Delete']);
        $this->assertTrue($result);

        $data = $this->database->get(['name' => 'User to Delete']);
        $this->assertCount(0, $data);
    }
}
