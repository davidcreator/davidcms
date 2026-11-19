<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * The explicit allowlist of gateway adapters installed in this deployment.
 *
 * DB values never become class names: every controller/store/service resolves
 * providers through this registry. Adding a gateway means implementing
 * GatewayAdapter, listing it here and shipping its capability matrix and
 * tests — nothing else in the core changes.
 */
final class InstalledGatewayAdapters {
    /** @return GatewayRegistry Registry with every installed adapter. */
    public static function registry(): GatewayRegistry {
        return new GatewayRegistry([
            new MercadoPagoGatewayAdapter(),
            new StripeCheckoutAdapter(),
            new PaypalOrdersAdapter(),
        ]);
    }

    /** @return list<string> Installed provider codes in display order. */
    public static function codes(): array {
        $codes = [];

        foreach (self::registry()->all() as $adapter) {
            $codes[] = $adapter->code();
        }

        return $codes;
    }

    public static function isInstalled(string $code): bool {
        try {
            self::registry()->get($code);

            return true;
        } catch (\OutOfBoundsException) {
            return false;
        }
    }
}
