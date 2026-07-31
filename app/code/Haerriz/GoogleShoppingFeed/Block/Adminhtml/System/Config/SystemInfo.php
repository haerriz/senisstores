<?php
namespace Haerriz\GoogleShoppingFeed\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\App\State;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;

class SystemInfo extends Field
{
    private $appState;
    private $directoryList;
    private $resourceConnection;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        State $appState,
        DirectoryList $directoryList,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->appState = $appState;
        $this->directoryList = $directoryList;
        $this->resourceConnection = $resourceConnection;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $mode = $this->appState->getMode();
        $path = $this->directoryList->getRoot();
        $user = get_current_user();
        
        $connection = $this->resourceConnection->getConnection();
        $dbTime = $connection->fetchOne('SELECT NOW()') ?? date('Y-m-d H:i:s');
        
        $opcacheActive = function_exists('opcache_get_status') && opcache_get_status() !== false ? 'Active' : 'Inactive';

        $html = '<div style="background:#f8f9fa; border:1px solid #d2d6dc; padding:15px; border-radius:6px;">';
        $html .= '<h4 style="margin-top:0; color:#eb5202;">System Information</h4>';
        $html .= '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
        $html .= '<tr><td style="padding:6px; font-weight:600; width:200px;">Magento Mode:</td><td>' . htmlspecialchars(ucfirst($mode)) . '</td></tr>';
        $html .= '<tr><td style="padding:6px; font-weight:600;">Magento Root Path:</td><td><code>' . htmlspecialchars($path) . '</code></td></tr>';
        $html .= '<tr><td style="padding:6px; font-weight:600;">Server User:</td><td>' . htmlspecialchars($user) . '</td></tr>';
        $html .= '<tr><td style="padding:6px; font-weight:600;">Current DB Time:</td><td><code>' . htmlspecialchars($dbTime) . '</code></td></tr>';
        $html .= '<tr><td style="padding:6px; font-weight:600;">Opcache Status:</td><td><strong style="color:' . ($opcacheActive === 'Active' ? '#2e7d32' : '#d32f2f') . '">' . $opcacheActive . '</strong></td></tr>';
        $html .= '</table></div>';

        return $html;
    }
}
