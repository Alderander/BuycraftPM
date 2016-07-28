<?php

namespace Buycraft\PocketMine;

use Buycraft\PocketMine\Commands\BuycraftCommand;
use Buycraft\PocketMine\Execution\CommandExecutor;
use Buycraft\PocketMine\Execution\DeleteCommandsTask;
use Buycraft\PocketMine\Execution\DuePlayerCheck;
use pocketmine\plugin\PluginBase;

class BuycraftPlugin extends PluginBase
{
    private static $instance;
    private $pluginApi;
    private $commandExecutionTask;
    private $deleteCommandsTask;
    private $serverInformation;
    private $allDue = array();

    public function onEnable()
    {
        $this->saveDefaultConfig();
        $secret = $this->getConfig()->get('secret');
        if ($secret)
        {
            $api = new PluginApi($secret);
            try
            {
                $this->verifyInformation($api);
                $this->pluginApi = $api;
                $this->startInitialTasks();
            }
            catch (\Exception $e)
            {
                $this->getLogger()->warning("Unable to verify information");
                $this->getLogger()->logException($e);
            }
        }
        else
        {
            $this->getLogger()->info("Looks like this is your first time using Buycraft. Set up your server by using 'buycraft secret <key>'.");
        }

        self::$instance = $this;

        $this->getServer()->getPluginManager()->registerEvents(new BuycraftListener(), $this);
        $this->getServer()->getCommandMap()->register("buycraft", new BuycraftCommand($this));
    }

    public function onDisable()
    {
        $this->saveConfig();
    }

    /**
     * @return PluginApi
     */
    public function getPluginApi()
    {
        return $this->pluginApi;
    }

    /**
     * @return CommandExecutor
     */
    public function getCommandExecutionTask()
    {
        return $this->commandExecutionTask;
    }

    /**
     * @return mixed
     */
    public function getDeleteCommandsTask()
    {
        return $this->deleteCommandsTask;
    }

    /**
     * @return BuycraftPlugin
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * @return array
     */
    public function getAllDue(): array
    {
        return $this->allDue;
    }

    /**
     * @param array $allDue
     */
    public function setAllDue(array $allDue)
    {
        // Because PHP logic.
        $this->allDue = (array)$allDue;
    }

    private function verifyInformation(PluginApi $api)
    {
        $this->serverInformation = $api->basicGet("/information");

        // Nag if the store is in online mode
        if ($this->serverInformation->account->online_mode)
        {
            $this->getLogger()->warning("Your Buycraft store is set to online mode. As Minecraft Pocket Edition " .
                "has no username authentication, this is likely a mistake.");
            $this->getLogger()->warning("This message is safe to ignore, but you may wish to use a separate web store set to offline mode.");
        }
    }

    /**
     * Attempts to change the current secret key. Will not always work, but due to the "design" of threaded PHP, this
     * is the only way we can accomplish this.
     * @param $key string
     */
    public function changeSecret($key)
    {
        $newApi = new PluginApi($key);
        $this->verifyInformation($newApi);

        // If we got here, the key seems to work.
        $this->pluginApi = $newApi;
        $this->getServer()->getScheduler()->cancelTasks($this);
        $this->startInitialTasks();
    }

    private function startInitialTasks()
    {
        $this->commandExecutionTask = new CommandExecutor();
        $this->getServer()->getScheduler()->scheduleRepeatingTask($this->commandExecutionTask, 1);
        $this->deleteCommandsTask = new DeleteCommandsTask($this->pluginApi);
        $this->getServer()->getScheduler()->scheduleRepeatingTask($this->deleteCommandsTask, 20);
        $this->getServer()->getScheduler()->scheduleAsyncTask(new DuePlayerCheck($this->pluginApi, true));
    }

    /**
     * @return mixed
     */
    public function getServerInformation()
    {
        return $this->serverInformation;
    }


}