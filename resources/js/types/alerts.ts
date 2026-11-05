/**
 * What needs attention now, as the server computes it for the signed-in user. The same figures feed
 * the alerts of the dashboard and the notification bell.
 */
export type Alerts = {
    late_advances: { count: number; amount: number };
    blocked_orders: number;
    blocked_after_days: number;
    client_cap: { used: number; cap: number; reached: boolean } | null;
    attachments: {
        missing: number;
        expiring: number;
        expired: number;
    } | null;
};
