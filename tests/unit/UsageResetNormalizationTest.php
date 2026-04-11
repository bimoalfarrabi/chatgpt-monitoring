<?php

use App\Controllers\Api\SubscriptionsController;
use App\Controllers\WebController;
use App\Models\AccountUsageModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UsageResetNormalizationTest extends CIUnitTestCase
{
    /**
     * @return list<array{class-string}>
     */
    public static function controllerProvider(): array
    {
        return [
            [WebController::class],
            [SubscriptionsController::class],
        ];
    }

    /**
     * @dataProvider controllerProvider
     *
     * @param class-string $controllerClass
     */
    public function testNormalizeUsageRowResetsToHundredWhenResetAtHasPassed(string $controllerClass): void
    {
        $controller = new $controllerClass();
        $mockUsageModel = $this->createMock(AccountUsageModel::class);

        $mockUsageModel->expects($this->once())
            ->method('update')
            ->with(
                12,
                [
                    'remaining_percent' => 100,
                    'reset_at' => null,
                ],
            );

        $this->setPrivateProperty($controller, 'usages', $mockUsageModel);

        $usage = [
            'id' => 12,
            'remaining_percent' => 35,
            'reset_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        ];

        $invokeNormalize = $this->getPrivateMethodInvoker($controller, 'normalizeUsageRow');
        $normalized = $invokeNormalize($usage);

        $this->assertSame(100, $normalized['remaining_percent']);
        $this->assertNull($normalized['reset_at']);
    }

    /**
     * @dataProvider controllerProvider
     *
     * @param class-string $controllerClass
     */
    public function testNormalizeUsageRowClearsResetAtWhenPercentAlreadyHundred(string $controllerClass): void
    {
        $controller = new $controllerClass();
        $mockUsageModel = $this->createMock(AccountUsageModel::class);

        $mockUsageModel->expects($this->once())
            ->method('update')
            ->with(
                20,
                ['reset_at' => null],
            );

        $this->setPrivateProperty($controller, 'usages', $mockUsageModel);

        $usage = [
            'id' => 20,
            'remaining_percent' => 100,
            'reset_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ];

        $invokeNormalize = $this->getPrivateMethodInvoker($controller, 'normalizeUsageRow');
        $normalized = $invokeNormalize($usage);

        $this->assertSame(100, $normalized['remaining_percent']);
        $this->assertNull($normalized['reset_at']);
    }

    /**
     * @dataProvider controllerProvider
     *
     * @param class-string $controllerClass
     */
    public function testNormalizeUsageRowKeepsUsageWhenResetAtNotPassedAndBelowHundred(string $controllerClass): void
    {
        $controller = new $controllerClass();
        $mockUsageModel = $this->createMock(AccountUsageModel::class);

        $mockUsageModel->expects($this->never())
            ->method('update');

        $this->setPrivateProperty($controller, 'usages', $mockUsageModel);

        $usage = [
            'id' => 31,
            'remaining_percent' => 45,
            'reset_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ];

        $invokeNormalize = $this->getPrivateMethodInvoker($controller, 'normalizeUsageRow');
        $normalized = $invokeNormalize($usage);

        $this->assertSame(45, $normalized['remaining_percent']);
        $this->assertSame($usage['reset_at'], $normalized['reset_at']);
    }
}
