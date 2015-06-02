<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use DreamFactory\Library\Utility\Enums\Verbs;

class SystemServiceTest extends \DreamFactory\Rave\Testing\TestCase
{
    const RESOURCE = 'service';

    protected $serviceId = 'system';

    public function tearDown()
    {
        $this->deleteDbService( 1 );
        $this->deleteDbService( 2 );
        $this->deleteDbService( 3 );

        parent::tearDown();
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGET()
    {
        $rs = $this->makeRequest( Verbs::GET );
        $content = json_encode( $rs->getContent(), JSON_UNESCAPED_SLASHES );

        $this->assertContains( '"resource":', $content );
    }

    public function testGETService()
    {
        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE );

        $this->assertContains( '"record":', json_encode( $rs->getContent(), JSON_UNESCAPED_SLASHES ) );
    }

    public function testGETServiceById()
    {
        $id = $this->createDbService( 1 );
        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE . '/' . $id );
        $data = $rs->getContent();
        $this->assertTrue( $data['id'] == $id );
    }

    public function testGETServiceByIds()
    {
        $id1 = $this->createDbService( 1 );
        $id2 = $this->createDbService( 2 );
        $id3 = $this->createDbService( 3 );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'ids' => "$id1,$id2,$id3" ] );
        $data = $rs->getContent();
        $ids = implode( ",", array_column( $data['record'], 'id' ) );

        $this->assertTrue( $ids == "$id1,$id2,$id3" );
    }

    public function testGETOverPOSTTunnel()
    {
        $id1 = $this->createDbService( 1 );
        $id2 = $this->createDbService( 2 );

        $payload = '[{"id":' . $id1 . '},{"id":' . $id2 . '}]';

        // Using GET here as payload is set manually and also X-HTTP-METHOD is used inside the RestController
        // but this test is not using RestController.
        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ ], $payload );

        $data = $rs->getContent();
        $label = implode( ",", array_column( $data['record'], 'label' ) );

        $this->assertEquals( "Database1,Database2", $label );
    }

    public function testResourceNotFound()
    {
        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\NotFoundException', 'Record not found.' );
        $this->makeRequest( Verbs::GET, static::RESOURCE . '/foo' );
    }

    /************************************************
     * Testing POST
     ************************************************/

    public function testPOSTService()
    {
        $payload =
            '{"record":[{"name":"db9","label":"Database","description":"Local Database", "is_active":1, "type":"sql_db", "config":{"dsn":"foo","username":"user","password":"pass"}}]}';

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ ], $payload );
        $data = $rs->getContent();
        $this->deleteDbService( 9 );
        $this->assertTrue( $data['id'] > 0 );
    }

    public function testPOSTServiceWithFields()
    {
        $payload =
            '{"record":[{"name":"db9","label":"Database","description":"Local Database", "is_active":1, "type":"sql_db", "config":{"dsn":"foo","username":"user","password":"pass"}}]}';

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ 'fields' => 'name,label,is_active' ], $payload );
        $this->deleteDbService( 9 );
        $this->assertTrue( json_encode( $rs->getContent(), JSON_UNESCAPED_SLASHES ) == '{"name":"db9","label":"Database","is_active":1}' );
    }

    public function testPOSTServiceMultiple()
    {
        $payload = '{
            "record":[
                {"name":"db9","label":"Database","description":"Local Database", "is_active":1, "type":"sql_db"},
                {"name":"db10","label":"MyDB","description":"Remote Database", "is_active":1, "type":"sql_db"}
            ]
        }';

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ ], $payload );
        $data = $rs->getContent();
        $this->deleteDbService( 9 );
        $this->deleteDbService( 10 );
        $this->assertTrue( is_array( $data['record'] ) );

        foreach ( $data['record'] as $r )
        {
            $this->assertTrue( $r['id'] > 0 );
        }
    }

    public function testPOSTServiceMultipleWithContinue()
    {
        $id1 = $this->createDbService( 1 );
        $id1++;
        $payload = '{
            "record":[
                {"name":"db9","label":"Database","description":"Local Database", "is_active":1, "type":"sql_db"},
                {"name":"db1","label":"MyDB","description":"Remote Database", "is_active":1, "type":"sql_db"}
            ]
        }';

        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\NotFoundException', 'Record not found.' );
        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\BadRequestException', 'Batch Error: Not all parts of the request were successful.' );

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ 'continue' => 'true' ], $payload );
    }

    public function testPOSTServiceMultipleWithRollback()
    {
        $this->deleteDbService( 9 );
        $this->createDbService( 1 );
        $payload = '{
            "record":[
                {"name":"db9","label":"Database","description":"Local Database", "is_active":1, "type":"sql_db"},
                {"name":"db1","label":"MyDB","description":"Remote Database", "is_active":1, "type":"sql_db"}
            ]
        }';

        $this->setExpectedException( '\Illuminate\Database\QueryException' );

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ 'rollback' => '1' ], $payload );
    }

    public function testPOSTServiceMultipleNoWrap()
    {
        $this->deleteDbService( 9 );
        $payload = '
            [
                {"name":"db9","label":"Database","description":"Local Database", "is_active":1, "type":"sql_db"},
                {"name":"db10","label":"MyDB","description":"Remote Database", "is_active":1, "type":"sql_db"}
            ]
        ';

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [], $payload );
        $data = $rs->getContent();
        $this->deleteDbService( 9 );
        $this->deleteDbService( 10 );
        $this->assertTrue( is_array( $data['record'] ) );

        foreach ( $data['record'] as $r )
        {
            $this->assertTrue( $r['id'] > 0 );
        }
    }

    public function testPOSTServiceSingleRecord()
    {
        $payload = '{
                        "name":"db1",
                        "label":"Database",
                        "description":"Local Database",
                        "is_active":1,
                        "type":"sql_db"
                    }';

        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\BadRequestException');
        $this->makeRequest( Verbs::POST, static::RESOURCE, [], $payload );
    }

    public function testPOSTServiceMissingNotNullField()
    {
        $payload = '[{
                        "label":"Database",
                        "description":"Local Database",
                        "is_active":1,
                        "type":"sql_db"
                    }]';

        $this->setExpectedException('\PDOException');
        $this->makeRequest( Verbs::POST, static::RESOURCE, [], $payload );
    }

    /************************************************
     * Testing PUT
     ************************************************/

    public function testPUTServiceById()
    {
        $this->testPATCHServiceById( Verbs::PUT );
    }

    public function testPUTServiceByIds()
    {
        $this->testPATCHServiceByIds( Verbs::PUT );
    }

    public function testPUTServiceBulk()
    {
        $this->testPATCHServiceBulk( Verbs::PUT );
    }

    /************************************************
     * Testing PATCH
     ************************************************/

    public function testPATCHServiceById( $verb = Verbs::PATCH )
    {
        $id1 = $this->createDbService(1);
        $payload = '{
                        "description":"unit-test-string"
                    }';

        $rs = $this->makeRequest( $verb, static::RESOURCE.'/'.$id1, [], $payload );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains( '{"id":'.$id1.'}', $content );

        $result = $this->makeRequest( Verbs::GET, static::RESOURCE.'/'.$id1 );
        $resultArray = $result->getContent();

        $this->assertEquals( "unit-test-string", $resultArray['description'] );
    }

    public function testPATCHServiceByIds( $verb = Verbs::PATCH )
    {
        $id1 = $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $payload = '[{
                        "description":"unit-test-description",
                        "label":"unit-test-label"
                    }]';

        $rs = $this->makeRequest( $verb, static::RESOURCE, ['ids'=>"$id1,$id2,$id3"], $payload );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains( '{"record":[{"id":'.$id1.'},{"id":'.$id2.'},{"id":'.$id3.'}]}', $content );

        $result = $this->makeRequest( Verbs::GET, static::RESOURCE, ['ids'=>"$id1,$id2,$id3"] );
        $ra = $result->getContent();

        $dColumn = implode( ",", array_column( $ra['record'], 'description' ) );
        $lColumn = implode( ",", array_column( $ra['record'], 'label' ) );

        $this->assertEquals( "unit-test-description,unit-test-description,unit-test-description", $dColumn );
        $this->assertEquals( "unit-test-label,unit-test-label,unit-test-label", $lColumn );
    }

    public function testPATCHServiceBulk( $verb = Verbs::PATCH )
    {
        $id1 = $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $payload = '[{
                        "id":'.$id1.',
                        "description":"unit-test-d1",
                        "label":"unit-test-l1"
                    },
                    {
                        "id":'.$id2.',
                        "description":"unit-test-d2",
                        "label":"unit-test-l2"
                    },
                    {
                        "id":'.$id3.',
                        "description":"unit-test-d3",
                        "label":"unit-test-l3"
                    }]';

        $rs = $this->makeRequest( $verb, static::RESOURCE, [], $payload );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains( '{"record":[{"id":'.$id1.'},{"id":'.$id2.'},{"id":'.$id3.'}]}', $content );

        $result = $this->makeRequest( Verbs::GET, static::RESOURCE, ['ids'=>"$id1,$id2,$id3"] );
        $ra = $result->getContent();
        $dColumn = implode( ",", array_column( $ra['record'], 'description' ) );
        $lColumn = implode( ",", array_column( $ra['record'], 'label' ) );

        $this->assertEquals( "unit-test-d1,unit-test-d2,unit-test-d3", $dColumn );
        $this->assertEquals( "unit-test-l1,unit-test-l2,unit-test-l3", $lColumn );
    }

    public function testPATCHServiceBulkWithFields( $verb = Verbs::PATCH )
    {
        $id1 = $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $payload = '[{
                        "id":'.$id1.',
                        "description":"unit-test-d1",
                        "label":"unit-test-l1"
                    },
                    {
                        "id":'.$id2.',
                        "description":"unit-test-d2",
                        "label":"unit-test-l2"
                    },
                    {
                        "id":'.$id3.',
                        "description":"unit-test-d3",
                        "label":"unit-test-l3"
                    }]';


        $rs = $this->makeRequest( $verb, static::RESOURCE, ['fields'=>'label'], $payload );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);

        $this->assertContains( '{"record":[{"label":"unit-test-l1"},{"label":"unit-test-l2"},{"label":"unit-test-l3"}]}', $content );
    }

    public function testPATCHServiceBulkWithContinue( $verb = Verbs::PATCH )
    {
        $id1 = $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $payload = '[{
                        "id":'.$id1.',
                        "description":"unit-test-d1",
                        "label":"unit-test-l1"
                    },
                    {
                        "id":'.$id2.',
                        "name":"db1",
                        "description":"unit-test-d2",
                        "label":"unit-test-l2"
                    },
                    {
                        "id":'.$id3.',
                        "description":"unit-test-d3",
                        "label":"unit-test-l3"
                    }]';


        try
        {
            $this->makeRequest( $verb, static::RESOURCE, [ 'continue' => '1' ], $payload );
        }
        catch(\DreamFactory\Rave\Exceptions\BadRequestException $e)
        {

            $this->assertEquals(400, $e->getStatusCode());
            $this->assertContains( 'Batch Error: Not all parts of the request were successful.', $e->getMessage() );

            $result = $this->makeRequest( Verbs::GET, static::RESOURCE, ['ids'=>"$id1,$id2,$id3"] );
            $ra = $result->getContent();
            $dColumn = implode( ",", array_column( $ra['record'], 'description' ) );
            $lColumn = implode( ",", array_column( $ra['record'], 'label' ) );

            $this->assertEquals( "unit-test-d1,Local Database2,unit-test-d3", $dColumn );
            $this->assertEquals( "unit-test-l1,Database2,unit-test-l3", $lColumn );
        }
    }


    public function testPATCHServiceBulkWithRollback( $verb = Verbs::PATCH )
    {
        $id1 = $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $payload = '[{
                        "id":'.$id1.',
                        "description":"unit-test-d1",
                        "label":"unit-test-l1"
                    },
                    {
                        "id":'.$id2.',
                        "name":"db1",
                        "description":"unit-test-d2",
                        "label":"unit-test-l2"
                    },
                    {
                        "id":'.$id3.',
                        "description":"unit-test-d3",
                        "label":"unit-test-l3"
                    }]';

        try
        {
            $this->makeRequest( $verb, static::RESOURCE, [ 'rollback' => 'true' ], $payload );
        }
        catch(\DreamFactory\Rave\Exceptions\InternalServerErrorException $e)
        {
            $this->assertEquals(500, $e->getStatusCode());
            $this->assertContains("Integrity constraint violation: 1062 Duplicate entry 'db1' for key 'service_name_unique'", $e->getMessage());
            $result = $this->makeRequest( Verbs::GET, static::RESOURCE, ['ids'=>"$id1,$id2,$id3"] );
            $ra = $result->getContent();
            $dColumn = implode( ",", array_column( $ra['record'], 'description' ) );
            $lColumn = implode( ",", array_column( $ra['record'], 'label' ) );

            $this->assertEquals( "Local Database1,Local Database2,Local Database3", $dColumn );
            $this->assertEquals( "Database1,Database2,Database3", $lColumn );
        }
    }

    /************************************************
     * Testing DELETE
     ************************************************/

    public function testDELETEServiceById()
    {
        $id1 = $this->createDbService(1);
        $rs = $this->makeRequest( Verbs::DELETE, static::RESOURCE.'/'.$id1 );
        $this->assertEquals( '{"id":'.$id1.'}', json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES) );

        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\NotFoundException', 'Record not found.' );
        $this->makeRequest( Verbs::GET, static::RESOURCE.'/'.$id1 );
    }

    public function testDELETEServiceByIds()
    {
        $id1 = $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $rs = $this->makeRequest( Verbs::DELETE, static::RESOURCE, ['ids'=>"$id1,$id3"] );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);
        $this->assertEquals( '{"record":[{"id":'.$id1.'},{"id":'.$id3.'}]}', $content );

        try
        {
            $this->makeRequest( Verbs::GET, static::RESOURCE . '/' . $id1 );
        }
        catch(\DreamFactory\Rave\Exceptions\NotFoundException $e)
        {
            $this->assertEquals(404, $e->getStatusCode());
            $rs = $this->makeRequest( Verbs::GET, static::RESOURCE.'/'. $id2 );
            $data = $rs->getContent();
            $this->assertEquals( "Database2", $data['label'] );
        }
    }

    public function testDELETEServiceBulk()
    {
        $id1 = $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $payload = '[{"id":'.$id2.'},{"id":'.$id3.'}]';

        $rs = $this->makeRequest( Verbs::DELETE, static::RESOURCE, [], $payload );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);
        $this->assertEquals( '{"record":[{"id":'.$id2.'},{"id":'.$id3.'}]}', $content );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE.'/'.$id1 );
        $data = $rs->getContent();
        $this->assertEquals( "Database1", $data['label'] );


        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\NotFoundException', 'Record not found.' );
        $this->makeRequest( Verbs::GET, static::RESOURCE.'/'.$id3 );
    }

    public function testDELETEServiceBulkWithFields()
    {
        $this->createDbService(1);
        $id2 = $this->createDbService(2);
        $id3 = $this->createDbService(3);

        $payload = '[{"id":'.$id2.'},{"id":'.$id3.'}]';

        $rs = $this->makeRequest( Verbs::DELETE, static::RESOURCE, ['fields'=>'name,type'], $payload );
        $content = json_encode($rs->getContent(), JSON_UNESCAPED_SLASHES);
        $this->assertEquals( '{"record":[{"name":"db2","type":"sql_db"},{"name":"db3","type":"sql_db"}]}', $content );
    }

    /************************************************
     * Internal functions
     ************************************************/

    protected function deleteDbService( $num )
    {
        $serviceName = 'db' . $num;
        $service = \DreamFactory\Rave\Models\Service::whereName( $serviceName );
        $service->delete();

        return true;
    }

    protected function createDbService( $num )
    {
        $serviceName = 'db' . $num;
        $service = \DreamFactory\Rave\Models\Service::whereName( $serviceName )->first();

        if ( empty( $service ) )
        {
            $service = \DreamFactory\Rave\Models\Service::create(
                [
                    "name"        => $serviceName,
                    "label"       => "Database" . $num,
                    "description" => "Local Database" . $num,
                    "is_active"   => 1,
                    "type"        => "sql_db",
                    'config'      => [
                        'dsn'        => 'foo',
                        'username'   => 'user',
                        'password'   => 'password',
                        'db'         => 'mydb',
                        'options'    => 'options',
                        'attributes' => 'attributes'
                    ]
                ]
            );
        }

        return $service->id;
    }
}