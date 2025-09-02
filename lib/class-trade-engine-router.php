<?php
/**
 * @plugin Trade Engine
 * Picks Scalp or Swing engine based on WP option.
 */
class TradeEngineRouter
{
    public static function scoreAll(array $byTf, array $opts = []): array
    {
        $mode = get_option('trade_engine_mode', 'scalp'); // 'scalp' | 'swing'
        if ($mode === 'swing') {
            return TradeEngineSwing::scoreAll($byTf, $opts);
        }
        return TradeEngine::scoreAll($byTf, $opts);
    }
}
