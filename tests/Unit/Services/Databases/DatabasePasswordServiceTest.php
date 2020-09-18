<?php

namespace Tests\Unit\Services\Databases;

use Mockery as m;
use Tests\TestCase;
use Pterodactyl\Models\Database;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Extensions\DynamicDatabaseConnection;
use Pterodactyl\Services\Databases\DatabasePasswordService;
use Pterodactyl\Contracts\Repository\DatabaseRepositoryInterface;

class DatabasePasswordServiceTest extends TestCase
{
    /**
     * @var \Illuminate\Database\ConnectionInterface|\Mockery\Mock
     */
    private $connection;

    /**
     * @var \Pterodactyl\Extensions\DynamicDatabaseConnection|\Mockery\Mock
     */
    private $dynamic;

    /**
     * @var \Illuminate\Contracts\Encryption\Encrypter|\Mockery\Mock
     */
    private $encrypter;

    /**
     * @var \Pterodactyl\Contracts\Repository\DatabaseRepositoryInterface|\Mockery\Mock
     */
    private $repository;

    /**
     * Setup tests.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->connection = m::mock(ConnectionInterface::class);
        $this->dynamic = m::mock(DynamicDatabaseConnection::class);
        $this->encrypter = m::mock(Encrypter::class);
        $this->repository = m::mock(DatabaseRepositoryInterface::class);
    }

    /**
     * Test that a password can be updated.
     */
    public function testPasswordIsChanged()
    {
        /** @var \Pterodactyl\Models\Database $model */
        $model = factory(Database::class)->make(['max_connections' => 0]);

        $this->connection->expects('transaction')->with(m::on(function ($closure) {
            return is_null($closure());
        }));

        $this->dynamic->expects('set')->with('dynamic', $model->database_host_id)->andReturnNull();

        $this->encrypter->expects('encrypt')->with(m::on(function ($string) {
            preg_match_all('/[!@+=.^-]/', $string, $matches, PREG_SET_ORDER);
            $this->assertTrue(count($matches) >= 2 && count($matches) <= 6, "Failed asserting that [{$string}] contains 2 to 6 special characters.");
            $this->assertTrue(strlen($string) === 24, "Failed asserting that [{$string}] is 24 characters in length.");

            return true;
        }))->andReturn('enc123');

        $this->repository->expects('withoutFreshModel')->withNoArgs()->andReturnSelf();
        $this->repository->expects('update')->with($model->id, ['password' => 'enc123'])->andReturn(true);

        $this->repository->expects('dropUser')->with($model->username, $model->remote)->andReturn(true);
        $this->repository->expects('createUser')->with($model->username, $model->remote, m::any(), 0)->andReturn(true);
        $this->repository->expects('assignUserToDatabase')->with($model->database, $model->username, $model->remote)->andReturn(true);
        $this->repository->expects('flush')->withNoArgs()->andReturn(true);

        $response = $this->getService()->handle($model);
        $this->assertNotEmpty($response);
    }

    /**
     * Return an instance of the service with mocked dependencies.
     *
     * @return \Pterodactyl\Services\Databases\DatabasePasswordService
     */
    private function getService(): DatabasePasswordService
    {
        return new DatabasePasswordService($this->connection, $this->repository, $this->dynamic, $this->encrypter);
    }
}