<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Cron;
use Haerriz\AgenticCommerce\Model\Audit\ToolAuditLogger;
class CleanupToolAudit
{
    public function __construct(private ToolAuditLogger $audit) {}
    public function execute(): void { $this->audit->cleanup(); }
}
