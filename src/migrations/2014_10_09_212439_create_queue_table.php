<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQueueTable extends Migration {

    /**
     * Name of queue table.
     *
     * @var string
     */
    protected $table;

    /**
     * Initialize the migration instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->table = \Illuminate\Support\Facades\
        Config::get('queue.connections.mysql.table', 'queue');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->table, function($table)
        {
            $table->bigIncrements('ID');
            $table->string('queue_name');
            $table->enum('status', ['deleted', 'pending', 'running']);
            $table->integer('attempts')->unsigned();
            $table->longText('payload');
            $table->bigInteger('fireon');
            $table->string('key')->unique()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists($this->table);
    }

}
