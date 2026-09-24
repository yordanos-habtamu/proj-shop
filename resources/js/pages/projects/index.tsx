import { Head, Link, router } from '@inertiajs/react';
import { PackagePlus, PackageX } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge, badgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    create,
    destroy,
    edit,
    mine,
    submitForReview,
} from '@/routes/projects';
import {
    completenessLabels,
    formatPrice,
    statusLabels,
    type ProjectListing,
    type ProjectStatus,
} from '@/types/projects';
import { cn } from '@/lib/utils';

type IndexProps = {
    projects: ProjectListing[];
};

const statusBadge: Record<ProjectStatus, string> = {
    draft: 'outline',
    pending_review: 'secondary',
    approved: 'default',
    rejected: 'destructive',
    delisted: 'outline',
};

export default function MyProjects({ projects }: IndexProps) {
    const confirmDelete = (project: ProjectListing) => {
        if (
            window.confirm(
                `Delete "${project.title}"? This removes the archive permanently.`,
            )
        ) {
            router.delete(destroy.url({ project: project.id }));
        }
    };

    const submitForReviewAction = (project: ProjectListing) => {
        router.post(submitForReview.url({ project: project.id }));
    };

    return (
        <>
            <Head title="My Projects" />

            <Heading
                title="My Projects"
                description="Upload, edit and track your marketplace listings"
            />

            <div className="mb-6 flex items-center justify-between">
                <p className="text-muted-foreground text-sm">
                    {projects.length === 0
                        ? 'No listings yet.'
                        : `${projects.length} listing${projects.length === 1 ? '' : 's'}`}
                </p>
                <Button asChild>
                    <Link href={create()}>
                        <PackagePlus />
                        New project
                    </Link>
                </Button>
            </div>

            {projects.length === 0 && (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <PackageX className="text-muted-foreground size-8" />
                        <div>
                            <p className="font-medium">
                                You have not uploaded a project yet
                            </p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Upload a zip with your source code, set a price,
                                and it appears on the market after review.
                            </p>
                        </div>
                        <Button asChild variant="secondary">
                            <Link href={create()}>
                                Upload your first project
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            )}

            <div className="space-y-3">
                {projects.map((project) => (
                    <Card key={project.id}>
                        <CardContent className="flex flex-col gap-4 py-5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0 space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-base font-semibold">
                                        {project.title}
                                    </h3>
                                    <Badge
                                        className={cn(
                                            badgeVariants({
                                                variant: statusBadge[
                                                    project.status
                                                ] as never,
                                            }),
                                        )}
                                    >
                                        {statusLabels[project.status]}
                                    </Badge>
                                    <Badge variant="outline">
                                        {
                                            completenessLabels[
                                                project.completeness
                                            ]
                                        }
                                    </Badge>
                                </div>

                                {project.tagline && (
                                    <p className="text-muted-foreground text-sm">
                                        {project.tagline}
                                    </p>
                                )}

                                <p className="text-muted-foreground flex flex-wrap items-center gap-1 font-mono text-xs">
                                    {project.tech_stack?.map((tag) => (
                                        <span
                                            key={tag}
                                            className="bg-muted rounded px-1.5 py-0.5"
                                        >
                                            {tag}
                                        </span>
                                    )) ?? null}
                                </p>

                                {project.review_notes && (
                                    <p className="text-destructive text-sm">
                                        Review note:{' '}
                                        <span className="text-foreground">
                                            {project.review_notes}
                                        </span>
                                    </p>
                                )}
                            </div>

                            <div className="flex shrink-0 flex-col items-end gap-3">
                                <div className="text-right">
                                    <p className="text-lg font-semibold">
                                        {formatPrice(
                                            project.price_cents,
                                            project.currency,
                                        )}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {project.orders_count ?? 0} sale
                                        {(project.orders_count ?? 0) === 1
                                            ? ''
                                            : 's'}
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center justify-end gap-2">
                                    {(project.status === 'draft' ||
                                        project.status === 'rejected') && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                submitForReviewAction(project)
                                            }
                                        >
                                            Submit for review
                                        </Button>
                                    )}
                                    {project.status !== 'approved' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={edit.url({
                                                    project: project.id,
                                                })}
                                            >
                                                Edit
                                            </Link>
                                        </Button>
                                    )}
                                    {project.status !== 'approved' && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                confirmDelete(project)
                                            }
                                        >
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

MyProjects.layout = {
    breadcrumbs: [
        {
            title: 'My Projects',
            href: mine(),
        },
    ],
};
