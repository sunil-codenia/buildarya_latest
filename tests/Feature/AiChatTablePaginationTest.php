<?php

namespace Tests\Feature;

use Tests\TestCase;

class AiChatTablePaginationTest extends TestCase
{
    public function test_classic_view_contains_interactive_table_engine_and_styles()
    {
        $viewPath = resource_path('views/classic_view.blade.php');
        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        // Verify that all hardcoded ->limit(10) calls were removed from the initial queries
        $this->assertStringNotContainsString('->limit(10)', $content, 'Initial tenant DB queries should not hardcode ->limit(10)');

        // Verify CSS styling for AI interactive tables
        $this->assertStringContainsString('.ai-table-card', $content);
        $this->assertStringContainsString('.ai-table-toolbar', $content);
        $this->assertStringContainsString('.ai-table-search', $content);
        $this->assertStringContainsString('.ai-table-page-size', $content);
        $this->assertStringContainsString('.ai-th-sortable', $content);
        $this->assertStringContainsString('.ai-table-footer', $content);
        $this->assertStringContainsString('.ai-table-pagination', $content);
        $this->assertStringContainsString('.ai-page-btn', $content);

        // Verify JavaScript interactive table component functions
        $this->assertStringContainsString('function makeAiTableInteractive(tableEl)', $content);
        $this->assertStringContainsString('function enhanceAllAiTablesInContainer(container)', $content);
        $this->assertStringContainsString('enhanceAllAiTablesInContainer(aiRow)', $content);

        // Verify tables use class ai-interactive-table
        $this->assertStringContainsString('class="ai-interactive-table"', $content);
    }

    public function test_ai_chat_controller_renders_interactive_table_class()
    {
        $controllerPath = app_path('Http/Controllers/api/AiChatQueryController.php');
        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        // Verify table rendered with ai-interactive-table class
        $this->assertStringContainsString("<table class='ai-interactive-table'>", $content);
    }

    public function test_attendance_pdf_routes_and_download_links_remain_intact()
    {
        $webRoutesPath = base_path('routes/web.php');
        $content = file_get_contents($webRoutesPath);

        $this->assertStringContainsString('/attendance/download-pdf', $content);
        $this->assertStringContainsString('/attendance/export-pdf', $content);
    }
}
