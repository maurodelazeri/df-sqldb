<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSqlDbTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // SQL DB Service Configuration
        Schema::create(
            'sql_db_config',
            function ( Blueprint $t )
            {
                $t->integer( 'service_id' )->unsigned()->primary();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'services' )->onDelete( 'cascade' );
                $t->string( 'dsn' )->default( 0 );
                $t->string( 'username' )->nullable();
                $t->string( 'password' )->nullable();
                $t->string( 'db' )->nullable();
                $t->text( 'options' )->nullable();
                $t->text( 'attributes' )->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // SQL DB Service Configuration
        Schema::dropIfExists( 'sql_db_config' );
    }

}