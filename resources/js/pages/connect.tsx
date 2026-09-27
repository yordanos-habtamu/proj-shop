import { Head, router } from '@inertiajs/react';
import { Link2, Sparkles, Wallet } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { show, start } from '@/routes/connect';

type ConnectProps = {
    is_demo: boolean;
    connected: boolean;
    status?: string | null;
};

export default function Connect({ is_demo, connected, status }: ConnectProps) {
    return (
        <>
            <Head title="Stripe Connect" />

            <Heading
                title="Stripe Connect"
                description="Receive payouts for your sales directly into your bank account"
            />

            <Card>
                <CardContent className="space-y-6 py-6">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 text-primary rounded-md p-2.5">
                            <Wallet />
                        </div>
                        <div className="space-y-1">
                            <p className="font-medium">Payout account</p>
                            <p className="text-muted-foreground text-sm">
                                {connected
                                    ? 'Your account is connected and ready to receive payouts.'
                                    : 'Connect your Stripe account so buyers can pay you.'}
                            </p>
                        </div>
                        {connected && (
                            <Badge variant="default" className="ml-auto">
                                {status === 'active' ? 'Connected' : 'Pending'}
                            </Badge>
                        )}
                    </div>

                    {is_demo && (
                        <div className="bg-muted flex items-start gap-2 rounded-md p-3 text-sm">
                            <Sparkles className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            <p className="text-muted-foreground">
                                Stripe is in demo mode (STRIPE_ENABLED=false).
                                Connecting fakes a Stripe account so the full
                                checkout flow works offline.
                            </p>
                        </div>
                    )}

                    {!connected && (
                        <div className="flex justify-end">
                            <Button
                                onClick={() => router.post(start.url())}
                                data-test="connect-stripe-button"
                            >
                                <Link2 />
                                {is_demo
                                    ? 'Connect Stripe (demo)'
                                    : 'Connect with Stripe'}
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

Connect.layout = {
    breadcrumbs: [
        {
            title: 'Stripe Connect',
            href: show(),
        },
    ],
};
