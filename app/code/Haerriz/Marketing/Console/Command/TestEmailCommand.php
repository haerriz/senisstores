<?php
namespace Haerriz\Marketing\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\State;

class TestEmailCommand extends Command
{
    protected $transportBuilder;
    protected $storeManager;
    protected $inlineTranslation;
    protected $appState;

    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        StateInterface $inlineTranslation,
        State $appState
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
        $this->appState = $appState;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('haerriz:marketing:test-email')
             ->setDescription('Send a test marketing email')
             ->addArgument('email', InputArgument::REQUIRED, 'Test Email Address');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        
        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
        } catch (\Exception $e) {
            // Area code already set
        }

        try {
            $this->inlineTranslation->suspend();
            $storeId = $this->storeManager->getStore()->getId();
            if (!$storeId) {
                $storeId = 1;
            }
            
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('haerriz_marketing_template')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars(['subject' => 'TEST MARKETING EMAIL'])
                ->setFromByScope('general', $storeId)
                ->addTo($email)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();
            
            $output->writeln("<info>Test email successfully sent to $email</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Error sending test email: " . $e->getMessage() . "</error>");
            $this->inlineTranslation->resume();
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }
}
