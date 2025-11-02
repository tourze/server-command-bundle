<?php

declare(strict_types=1);

namespace ServerCommandBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Service\CommandOutputInspector;

/**
 * @covers \ServerCommandBundle\Service\CommandOutputInspector
 */
class CommandOutputInspectorTest extends TestCase
{
    private CommandOutputInspector $inspector;

    protected function setUp(): void
    {
        $this->inspector = new CommandOutputInspector();
    }

    /**
     * @test
     */
    public function hasErrorReturnsTrueForCommonErrorPatterns(): void
    {
        $errorOutputs = [
            'command not found: invalid-command',
            'Permission denied: /etc/shadow',
            'No such file or directory: /nonexistent',
            'cannot create directory: Permission denied',
            'Operation not permitted',
            'Access denied',
            'bash: line 1: syntax error',
            'Error: something went wrong',
            'ERROR: critical failure',
            'FAILED to complete operation',
            'cannot access /restricted: Permission denied',
            'not found: resource missing',
            'failed to execute command',
        ];

        foreach ($errorOutputs as $output) {
            $this->assertTrue(
                $this->inspector->hasError($output),
                "Expected error pattern not detected in: {$output}"
            );
        }
    }

    /**
     * @test
     */
    public function hasErrorReturnsFalseForSudoPasswordPrompts(): void
    {
        $sudoPrompts = [
            '[sudo] password for user:',
            '[sudo] password for admin: ',
            'Sorry, try again',
            '[sudo] password for testuser:',
        ];

        foreach ($sudoPrompts as $prompt) {
            $this->assertFalse(
                $this->inspector->hasError($prompt),
                "Sudo prompt incorrectly detected as error: {$prompt}"
            );
        }
    }

    /**
     * @test
     */
    public function hasErrorReturnsFalseForSuccessfulOutput(): void
    {
        $successOutputs = [
            'Operation completed successfully',
            'File created: /tmp/test.txt',
            'Command executed without errors',
            'Process finished with exit code 0',
            '',
            'Installing package...\nDone.',
        ];

        foreach ($successOutputs as $output) {
            $this->assertFalse(
                $this->inspector->hasError($output),
                "Successful output incorrectly detected as error: {$output}"
            );
        }
    }

    /**
     * @test
     */
    public function hasErrorReturnsTrueForMultilineOutputWithErrors(): void
    {
        $multilineOutput = "Starting process...\nConfiguring settings...\nError: Unable to connect to database\nRolling back changes...";

        $this->assertTrue($this->inspector->hasError($multilineOutput));
    }

    /**
     * @test
     */
    public function hasErrorReturnsFalseForMultilineOutputWithoutErrors(): void
    {
        $multilineOutput = "Starting process...\nConfiguring settings...\nInstalling dependencies...\nProcess completed successfully";

        $this->assertFalse($this->inspector->hasError($multilineOutput));
    }

    /**
     * @test
     */
    public function hasErrorHandlesCaseInsensitiveMatching(): void
    {
        $caseVariations = [
            'error: something failed',
            'ERROR: SOMETHING FAILED',
            'Error: Something Failed',
            'failed to complete',
            'FAILED TO COMPLETE',
            'Failed To Complete',
        ];

        foreach ($caseVariations as $output) {
            $this->assertTrue(
                $this->inspector->hasError($output),
                "Case variation not detected as error: {$output}"
            );
        }
    }

    /**
     * @test
     */
    public function hasErrorIgnoresSudoPromptsInMultilineOutput(): void
    {
        $outputWithSudoAndError = "[sudo] password for user:\nError: command failed";
        $outputWithSudoOnly = "[sudo] password for user:\nOperation completed";

        $this->assertTrue($this->inspector->hasError($outputWithSudoAndError));
        $this->assertFalse($this->inspector->hasError($outputWithSudoOnly));
    }
}