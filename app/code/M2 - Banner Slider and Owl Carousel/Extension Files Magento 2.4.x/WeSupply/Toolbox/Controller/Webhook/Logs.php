<?php

namespace WeSupply\Toolbox\Controller\Webhook;

use Magento\Backend\App\Response\Http\FileFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\FileSystemException;
use WeSupply\Toolbox\Helper\Data as WeSupplyHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

/**
 * Class Logs
 *
 * @package WeSupply\Toolbox\Controller\Webhook
 */
class Logs extends Action
{
    /** @var int  */
    const MAX_LOG_SIZE = 10000;

    /** @var string[]  */
    const LOG_TYPES = ['wesupply', 'exception', 'debug', 'system'];

    /**
     * @var int
     */
    protected $logSize = 1000;

    /**
     * @var string
     */
    protected $logType = 'error';

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var WeSupplyHelper
     */
    protected $helper;

    /**
     * @var Filesystem
     */
    protected $file;

    /**
     * Fetch constructor.
     * @param Context $context
     * @param WeSupplyHelper $helper
     * @param FileFactory $fileFactory
     * @param Filesystem $file
     */
    public function __construct(
        Context $context,
        WeSupplyHelper $helper,
        FileFactory $fileFactory,
        Filesystem $file
    )
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->fileFactory = $fileFactory;
        $this->file = $file;
    }

    public function execute()
    {
        $response = '';
        $params = $this->getRequest()->getParams();
        $validationError = $this->_validateParams($params);

        if ($validationError) {
            /** Add the error response */
            $errorMessage = '';
            foreach ($validationError as $error) {
                $errorMessage .= $error . PHP_EOL;
            }

            $response .= $errorMessage;
        } else {
            // fetch from file
            $logDirectory = $this->file->getDirectoryWrite(DirectoryList::LOG);
            $logFilesPath = $logDirectory->getAbsolutePath() . $this->logType . '.log';
            $errorsLogArray = $this->_readFileContent($logFilesPath, $this->logSize);
            $response .= implode("", $errorsLogArray);
        }

        return $this->_prepareResponse($response);
    }

    /**
     * @param string $response
     * @return \Magento\Framework\App\ResponseInterface
     * @throws FileSystemException
     */
    private function _prepareResponse($response)
    {
        $logDirectory = $this->file->getDirectoryWrite(DirectoryList::VAR_DIR);
        $logPath = 'wesupply';
        $fileName = $logPath . DS . $this->logType . '.log';

        try {
            $logDirectory->create($logPath);
        } catch (FileSystemException $ex) {
            $fileName = $this->logType . '.log';
        }

        return $this->fileFactory->create($fileName, $response, DirectoryList::VAR_DIR);
    }

    /**
     * @param $params
     * @return array|false
     */
    private function _validateParams($params)
    {
        $errors = [];
        $guid = $params['guid'] ?? false;
        if (!$guid) {
            $errors[] = 'Access Key is required.';
        }

        $logType = $params['logType'] ?? false;
        if (!$logType || !in_array($logType, self::LOG_TYPES)) {
            $errors[] = 'Log Type is required. One of the following should be used: ' . implode(",",self::LOG_TYPES);
        } else {
            $this->logType = $logType;
        }

        $logSize = $params['logSize'] ?? false;
        $logSize = intval($logSize);
        if ($logSize) {
            $this->logSize = $logSize;
            if ( $logSize > self::MAX_LOG_SIZE ) {
               $this->logSize = self::MAX_LOG_SIZE;
            }
        }

        $serverGuid = $this->helper->getGuidByScope();
        if ($serverGuid != $guid) {
            $errors[] = 'Access Key is invalid.';
        }

        if (!empty($errors)) {
            return $errors;
        }

        return false;

   }

    /**
     * @param string $file
     * @param int $lines
     * @return array
     */
   private function _readFileContent($file, $lines) {
       $handle = fopen($file, "r");
       $linecounter = $lines;
       $pos = -2;
       $beginning = false;
       $text = [];
       while ($linecounter > 0) {
           $t = " ";
           while ($t != "\n") {
               if(fseek($handle, $pos, SEEK_END) == -1) {
                   $beginning = true;
                   break;
               }
               $t = fgetc($handle);
               $pos --;
           }
           $linecounter --;
           if ($beginning) {
               rewind($handle);
           }
           $text[$lines-$linecounter-1] = fgets($handle);
           if ($beginning) break;
       }
       fclose ($handle);
       return array_reverse($text);
   }

}
