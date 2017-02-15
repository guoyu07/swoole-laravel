<?php
/**
 * Copyright
 *
 * (c) Huang Yi <coodeer@163.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HuangYi\Swoole\Commands;

use HuangYi\Swoole\Servers\HttpServer;
use Illuminate\Console\Command;
use Swoole\Http\Server;
use Swoole\Process;

class HttpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swoole:http {action : start|stop|restart}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Swoole http server command.';

    /**
     * The console command action. start|stop|restart
     *
     * @var string
     */
    protected $action;

    /**
     *
     * The PID.
     *
     * @var int
     */
    protected $pid;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $this->initAction();
        $this->runAction();
    }

    /**
     * Run action.
     */
    protected function runAction()
    {
        $this->{$this->action}();
    }

    /**
     * Run swoole http server.
     */
    protected function start()
    {
        $pid = $this->getPID();

        if ( $this->isRunning($pid) ) {
            $this->error('Swoole http server process is already running.');
            exit(1);
        }

        $swooleHttpServer = new Server('127.0.0.1', '1215');
        $httpServer = new HttpServer($swooleHttpServer);

        $httpServer->run();
    }

    /**
     * Stop swoole http server.
     */
    protected function stop()
    {
        $pid = $this->getPID();

        if ( ! $this->isRunning($pid) ) {
            $this->error("There is no Swoole http server process running.");
            exit(1);
        }

        $isRunning = $this->killProcess($pid, SIGINT, 15);

        if ( $isRunning ) {
            $isRunning = $this->killProcess($pid, SIGTERM, 15);
        }

        if ( $isRunning ) {
            $isRunning = $this->killProcess($pid, SIGKILL, 0);
        }

        if ( $isRunning ) {
            $this->error('Unable to stop the Swoole http server process.');
            exit(1);
        }

        $this->removePIDFile();
    }

    /**
     * Restart swoole http server.
     */
    protected function restart()
    {
        $this->stop();
        $this->start();
    }

    /**
     * Initialize command action.
     */
    protected function initAction()
    {
        $this->action = $this->argument('action');

        if ( ! in_array($this->action, ['start', 'stop', 'restart']) ) {
            $this->error("Invalid argument '{$this->action}'. Expected 'start', 'stop' or 'restart'.");
            exit(1);
        }
    }

    /**
     * If Swoole process is running.
     *
     * @param int $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        if ( ! $pid ) {
            return false;
        }

        Process::kill($pid, 0);

        return ! swoole_errno();
    }

    /**
     * Kill process.
     *
     * @param int $pid
     * @param int $sig
     * @param int $wait
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ( $wait ) {
            $start = time();

            do {
                if ( ! $this->isRunning($pid) ) {
                    break;
                }

                usleep(100000);
            } while ( time() < $start + $wait );
        }

        return $this->isRunning($pid);
    }

    /**
     * Get PID.
     *
     * @return int|null
     */
    protected function getPID()
    {
        if (  $this->pid ) {
            return $this->pid;
        }

        $pid = null;
        $path = $this->getPIDPath();

        if ( file_exists($path) ) {
            $pid = (int) file_get_contents($path);

            if ( ! $pid ) {
                $this->removePIDFile();
            } else {
                $this->pid = $pid;
            }
        }

        return $this->pid;
    }

    /**
     * Get PID file path.
     *
     * @return string
     */
    protected function getPIDPath()
    {
        return app('config')->get('swoole.pid_file');
    }

    /**
     * Remove PID file.
     */
    protected function removePIDFile()
    {
        if ( file_exists($this->getPIDPath()) ) {
            unlink($this->getPIDPath());
        }
    }
}
