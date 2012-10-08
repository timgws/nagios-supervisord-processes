#!/usr/bin/env php
<?php
/**
 * @see http://supervisord.org/api.html
 * @author jpauli
 */
class SuperVisorNagios
{
    const NAGIOS_STATE_OK        = 0;
    const NAGIOS_STATE_WARNING   = 1;
    const NAGIOS_STATE_CRITICAL  = 2;
    const NAGIOS_STATE_UNKNOWN   = 3;
    const NAGIOS_STATE_DEPENDENT = 4;
    
    /** @see http://supervisord.org/subprocess.html#process-states 
     * STOPPED (0)
       The process has been stopped due to a stop request or has never been started.
       
       STARTING (10)
       The process is starting due to a start request.

       RUNNING (20)
       The process is running.

       BACKOFF (30)
       The process entered the STARTING state but subsequently exited too quickly to move to the RUNNING state.
       
       STOPPING (40)
       The process is stopping due to a stop request.
       
       EXITED (100)
       The process exited from the RUNNING state (expectedly or unexpectedly).
       
       FATAL (200)
       The process could not be started successfully.
       
       UNKNOWN (1000)
       The process is in an unknown state (supervisord programming error). */
    const SUPERVISOR_STATE_STOPPED  = 0;
    const SUPERVISOR_STATE_STARTING = 10;
    const SUPERVISOR_STATE_RUNNING  = 20;
    const SUPERVISOR_STATE_BACKOFF  = 30;
    const SUPERVISOR_STATE_STOPPING = 40;
    const SUPERVISOR_STATE_EXITED   = 100;
    const SUPERVISOR_STATE_FATAL    = 200;
    const SUPERVISOR_STATE_UNKNOWN  = 1000;
    
    const SUPERVISOR_RPC_REQUEST_LISTPROCESS = 'supervisor.getAllProcessInfo';
    
    private $streamContext;
    private $serverName;
    private $grepPattern;
    
    public function __construct()
    {
        $this->streamContext = stream_context_create(array('http' => array(
        'method' => "POST",
        'header' => "Content-Type: text/xml",
        )));

        if(!extension_loaded("xmlrpc")) {
            $this->writeError("PHP extension ext/xmlrpc is needed but not found", self::NAGIOS_STATE_UNKNOWN);
        }
        
        if($_SERVER['argc'] != 5 || (($getopt = getopt("h:p:")) === false)) {
            $this->writeError("Wrong script usage : use -h <hostname> -p <pattern>", self::NAGIOS_STATE_UNKNOWN);
        }
        $this->serverName  = $getopt['h'];
        $this->grepPattern = preg_quote($getopt['p'], '/');    
    }

    private function xmlRPCRequest($request, array $params = array())
    {
        stream_context_set_option($this->streamContext, "http", "content", xmlrpc_encode_request($request, $params));
        $response = @file_get_contents("http://$this->serverName:9001/RPC2", false, $this->streamContext);
        
        if($response === false) {
            $lastError = error_get_last();
            $this->writeError("Could not contact server $this->serverName : {$lastError['message']}", self::NAGIOS_STATE_UNKNOWN);
        }
        $response = xmlrpc_decode($response);
        if ($response && xmlrpc_is_fault($response)) {
            $this->writeError("Could not decode XML-RPC : {$response['faultString']}", self::NAGIOS_STATE_UNKNOWN);
        }
        return $response;
    }
    
    public function process()
    {
        $superVisorProcessList = $this->xmlRPCRequest(self::SUPERVISOR_RPC_REQUEST_LISTPROCESS);
        $deadProcesses = array();

        foreach ($superVisorProcessList as $process) {
            if (isset($process['name']) && preg_match("/$this->grepPattern/",$process['name'])) {
        
                if($process['state'] != self::SUPERVISOR_STATE_RUNNING) {
                    $deadProcesses[$process['name']] = $process['spawnerr'];
                }
            }
        }

        if($deadProcesses) {
            $deadProcessList = implode(',', array_keys($deadProcesses));
            $this->writeError("Process $deadProcessList are not running", self::NAGIOS_STATE_CRITICAL);
        }

        $this->exitOk();
    }
    
    private function writeError($errorMsg, $exitStatus)
    {
        echo str_replace("\n", "", $errorMsg) . "\n";
        exit($exitStatus);
    }
    
    private function exitOk()
    {
        echo "All is right \n";
	exit(self::NAGIOS_STATE_OK);
    }
}

$nagios = new SuperVisorNagios();
$superVisorProcessList = $nagios->process();
