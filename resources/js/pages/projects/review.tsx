import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle2, Inbox, ShieldAlert } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge, badgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { approve, reject, review } from '@/routes/projects';
import {
    completenessLabels,
    formatPrice,
    type ProjectListing,
    type ScanSeverity,
} from '@/types/projects';

type ReviewProps = {
    projects: ProjectListing[];
};

const severityVariant: Record<ScanSeverity, string> = {
    high: 'destructive',
    medium: 'secondary',
    low: 'outline',
};

function ScanSummary({ project }: { project: ProjectListing }) {
    const scan = project.scan_report;

    if (!scan) {
        return (
            <p className="text-muted-foreground text-sm">
                Scan pending — report will appear once the queue is processed.
            </p>
        );
    }

    const high = scan.issues.filter(
        (issue) => issue.severity === 'high',
    ).length;
    const medium = scan.issues.filter(
        (issue) => issue.severity === 'medium',
    ).length;

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
                <Badge
                    variant={
                        scan.verdict === 'clean' ? 'default' : 'destructive'
                    }
                >
                    {scan.verdict === 'clean' ? 'Clean scan' : 'Flagged scan'}
                </Badge>
                <span className="text-muted-foreground text-xs">
                    {scan.files} file{scan.files === 1 ? '' : 's'} · {high} high
                    · {medium} medium
                </span>
            </div>

            {scan.issues.length > 0 && (
                <ul className="max-h-48 space-y-1.5 overflow-y-auto pr-1">
                    {scan.issues.map((issue, index) => (
                        <li
                            key={`${issue.rule}-${index}`}
                            className="bg-muted/50 flex items-start gap-2 rounded p-2 text-sm"
                        >
                            <Badge
                                className={badgeVariants({
                                    variant: severityVariant[
                                        issue.severity
                                    ] as never,
                                })}
                            >
                                {issue.severity}
                            </Badge>
                            <div className="min-w-0">
                                <p>{issue.message}</p>
                                {issue.path && (
                                    <p className="text-muted-foreground truncate font-mono text-xs">
                                        {issue.path}
                                    </p>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {scan.issues_truncated && (
                <p className="text-muted-foreground text-xs">
                    Some issues were omitted from this report.
                </p>
            )}
        </div>
    );
}

export default function ReviewQueue({ projects }: ReviewProps) {
    const [notes, setNotes] = useState<Record<number, string>>({});

    const updateNotes = (projectId: number, value: string) => {
        setNotes((current) => ({ ...current, [projectId]: value }));
    };

    const approveProject = (projectId: number) => {
        router.post(approve.url({ project: projectId }), {
            notes: notes[projectId] ?? '',
        });
    };

    const rejectProject = (projectId: number, title: string) => {
        if (
            window.confirm(
                `Reject "${title}"? The seller will be notified and the project will not be listed.`,
            )
        ) {
            router.post(reject.url({ project: projectId }), {
                notes: notes[projectId] ?? '',
            });
        }
    };

    return (
        <>
            <Head title="Review queue" />

            <Heading
                title="Review queue"
                description="Scan reports and moderation for pending projects"
            />

            {projects.length === 0 && (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <Inbox className="text-muted-foreground size-8" />
                        <div>
                            <p className="font-medium">Queue is clear</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                No projects are waiting for review right now.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            )}

            <div className="space-y-4">
                {projects.map((project) => (
                    <Card key={project.id}>
                        <CardContent className="space-y-4 py-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-base font-semibold">
                                            {project.title}
                                        </h3>
                                        <Badge variant="secondary">
                                            Pending review
                                        </Badge>
                                        <Badge variant="outline">
                                            {
                                                completenessLabels[
                                                    project.completeness
                                                ]
                                            }
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        <span className="font-medium">
                                            {project.seller
                                                ? project.seller.name
                                                : `Seller #${project.seller_id}`}
                                        </span>{' '}
                                        ·{' '}
                                        {formatPrice(
                                            project.price_cents,
                                            project.currency,
                                        )}
                                    </p>
                                    {project.tech_stack &&
                                        project.tech_stack.length > 0 && (
                                            <p className="text-muted-foreground flex flex-wrap items-center gap-1 font-mono text-xs">
                                                {project.tech_stack.map(
                                                    (tag) => (
                                                        <span
                                                            key={tag}
                                                            className="bg-muted rounded px-1.5 py-0.5"
                                                        >
                                                            {tag}
                                                        </span>
                                                    ),
                                                )}
                                            </p>
                                        )}
                                </div>
                            </div>

                            <div className="rounded-lg border p-3">
                                <ScanSummary project={project} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`notes-${project.id}`}>
                                    Moderator notes{' '}
                                    <span className="text-muted-foreground text-xs">
                                        (required to reject)
                                    </span>
                                </Label>
                                <Textarea
                                    id={`notes-${project.id}`}
                                    rows={3}
                                    value={notes[project.id] ?? ''}
                                    onChange={(event) =>
                                        updateNotes(
                                            project.id,
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Feedback you want shared with the seller..."
                                />
                            </div>

                            <div className="flex flex-wrap items-center justify-end gap-2">
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        rejectProject(project.id, project.title)
                                    }
                                >
                                    <ShieldAlert />
                                    Reject
                                </Button>
                                <Button
                                    onClick={() => approveProject(project.id)}
                                >
                                    <CheckCircle2 />
                                    Approve
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

ReviewQueue.layout = {
    breadcrumbs: [
        {
            title: 'Review queue',
            href: review(),
        },
    ],
};
