import { Form, Head } from '@inertiajs/react';
import { Percent } from 'lucide-react';
import FeeController from '@/actions/App/Http/Controllers/Admin/FeeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as feesIndex } from '@/routes/admin/fees';

type FeeHistoryEntry = {
    id: number;
    percent: number;
    is_active: boolean;
    creator?: string | null;
    created_at?: string | null;
};

type FeesProps = {
    currentPercent: number;
    history: FeeHistoryEntry[];
};

export default function Fees({ currentPercent, history }: FeesProps) {
    return (
        <>
            <Head title="Platform fees" />

            <Heading
                title="Platform fees"
                description="Set the commission cut applied to every sale on the marketplace"
            />

            <div className="space-y-6">
                <Card>
                    <CardContent className="space-y-6 py-6">
                        <div className="flex items-center gap-3">
                            <div className="bg-primary/10 text-primary rounded-md p-2.5">
                                <Percent />
                            </div>
                            <div>
                                <p className="font-medium">Current fee</p>
                                <p className="text-muted-foreground text-sm">
                                    Every sale is charged this percentage; the
                                    seller receives the remainder.
                                </p>
                            </div>
                            <Badge
                                variant="default"
                                className="ml-auto text-base"
                            >
                                {currentPercent}%
                            </Badge>
                        </div>

                        <Form
                            {...FeeController.update.form()}
                            className="grid gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="percent">
                                            Commission percentage
                                        </Label>
                                        <div className="flex max-w-xs items-center gap-2">
                                            <Input
                                                id="percent"
                                                type="number"
                                                min={0}
                                                max={100}
                                                step={1}
                                                defaultValue={currentPercent}
                                                name="percent"
                                                required
                                                className="w-28"
                                            />
                                            <span className="text-muted-foreground text-sm">
                                                %
                                            </span>
                                        </div>
                                        <InputError
                                            className="mt-2"
                                            message={errors.percent}
                                        />
                                    </div>

                                    <div className="flex items-center gap-4 pt-2">
                                        <Button
                                            disabled={processing}
                                            data-test="update-fee-button"
                                        >
                                            Save fee
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="py-5">
                        <h3 className="mb-3 text-sm font-semibold">
                            Fee history
                        </h3>
                        {history.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No fee changes recorded yet.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {history.map((fee) => (
                                    <li
                                        key={fee.id}
                                        className="flex items-center gap-3 py-2.5 text-sm"
                                    >
                                        <span className="font-medium">
                                            {fee.percent}%
                                        </span>
                                        {fee.is_active && (
                                            <Badge variant="default">
                                                Active
                                            </Badge>
                                        )}
                                        <span className="text-muted-foreground ml-auto text-xs">
                                            {fee.creator ?? 'Platform'} ·{' '}
                                            {fee.created_at}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Fees.layout = {
    breadcrumbs: [
        {
            title: 'Platform fees',
            href: feesIndex(),
        },
    ],
};
