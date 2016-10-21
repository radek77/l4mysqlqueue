<?php namespace Shamiao\L4mysqlqueue\Queue;

use Config;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\Queue;
use Illuminate\Queue\QueueInterface;
use ErrorException;

class MysqlQueue extends Queue implements QueueInterface
{

    /**
     * Name of queue table.
     *
     * @var string
     */
    protected $table;

    /**
     * The name of queue.
     *
     * @var string
     */
    protected $queue;

    /**
     * Create a new Mysql queue instance.
     *
     * @param null $queue
     * @internal param string $queue_name
     */
    public function __construct($queue = null)
    {
        $this->table = Config::get('queue.connections.mysql.table', 'queue');

        if ($queue === null) {
            $queue = 'default';
        }
        $this->queue = $queue;
    }

    /**
     * Push a new job onto the queue.
     *
     * $key is an optional unique key. If supplied a job will not be created if the $key matches an existing job.
     *
     * @param  string $job
     * @param  mixed  $data
     * @param  string $queue
     * @param  string $key
     * @param null    $delay
     * @return mixed
     */
    public function push($job, $data = '', $queue = null, $key = null, $delay = null)
    {
        if ($queue === null) {
            $queue = $this->queue;
        }
        if (!is_null($key)) {
            $result = DB::table($this->table)->where('queue_name', '=', $queue)->where('key', '=', $key)->get();
            if (!empty($result)) {
                // Should there be a meaningful return value?
                return 0;
            }
        }
        $options = ['key' => $key];
        if (isset($delay)) {
            $options['delay'] = $delay;
        }

        return $this->pushRaw($this->createPayload($job, $data), $queue, $options);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string $payload
     * @param  string $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        if ($queue === null) {
            $queue = $this->queue;
        }
        $key   = isset($options['key']) ? $options['key'] : null;
        $time  = isset($options['delay']) ? $options['delay'] : Carbon::now();
        $jobId = $this->insertJobRecord($payload, $time, $queue, $key);

        return 0;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTime|int $delay
     * @param  string        $job
     * @param  mixed         $data
     * @param  string        $queue
     * @param null           $key
     * @return mixed
     * @throws ErrorException
     */
    public function later($delay, $job, $data = '', $queue = null, $key = null)
    {
        if ($queue === null) {
            $queue = $this->queue;
        }
        if ($delay instanceof DateTime) {
            $time = $delay;
        } elseif (is_int($delay)) {
            $time = Carbon::now()->addSeconds($delay);
        } else {
            throw new ErrorException('DateTime or int $delay required. ');
        }
        $jobId = $this->push($job, $data, $queue, $key, $time);

        return 0;
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string $queue
     * @return \Illuminate\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        if ($queue === null) {
            $queue = $this->queue;
        }
        $query = DB::table($this->table)->where('queue_name', $queue)
            ->where('status', 'pending')
            ->where('fireon', '<', time())
            ->orderBy('fireon', 'asc');
        if ($query->count() == 0) {
            return null;
        }
        $record = $query->first();

        return new Jobs\MysqlJob($this->container, $record->ID, $record);
    }

    /**
     * Insert a job record into database.
     *
     * @param  string   $payload Payload string.
     * @param  DateTime $time Exact firing time of the job.
     * @param  string   $queue Queue name of the job.
     * @param  string   $key Optional unique key
     * @return int ID of new record inserted.
     * @throws ErrorException
     */
    private function insertJobRecord($payload, $time, $queue, $key = null)
    {
        if (!$time instanceof DateTime) {
            throw new ErrorException('An explicit DateTime value $time is required. ');
        }
        $jobId = DB::table($this->table)->insertGetId([
            'queue_name' => $queue,
            'payload'    => $payload,
            'status'     => 'pending',
            'attempts'   => 1,
            'fireon'     => $time->getTimestamp(),
            'key'        => $key
        ]);

        return $jobId;
    }
}
