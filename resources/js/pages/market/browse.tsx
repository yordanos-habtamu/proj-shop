import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { PackageOpen, Search } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as browse, show } from '@/routes/projects';
import {
    completenessLabels,
    formatPrice,
    type EnumOption,
    type ProjectListing,
} from '@/types/projects';

type Filters = {
    q: string;
    completeness: string;
    stack: string;
    min_price: string;
    max_price: string;
    sort: string;
};

type BrowseProps = {
    projects: ProjectListing[];
    filters: Record<string, string>;
    completenessOptions: EnumOption[];
    stacks: string[];
    count: number;
};

const sortOptions: EnumOption[] = [
    { value: 'newest', label: 'Newest' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
    { value: 'sales', label: 'Top sales' },
];

function CoverArt({ project }: { project: ProjectListing }) {
    return (
        <div className="bg-muted flex h-40 items-center justify-center overflow-hidden">
            {project.cover_url ? (
                <img
                    src={project.cover_url}
                    alt={`Cover for ${project.title}`}
                    className="h-full w-full object-cover"
                    loading="lazy"
                />
            ) : (
                <PackageOpen className="text-muted-foreground size-8" />
            )}
        </div>
    );
}

export default function Browse({
    projects,
    filters,
    completenessOptions,
    stacks,
    count,
}: BrowseProps) {
    const [draft, setDraft] = useState<Filters>({
        q: filters.q ?? '',
        completeness: filters.completeness ?? 'all',
        stack: filters.stack ?? 'all',
        min_price: filters.min_price ?? '',
        max_price: filters.max_price ?? '',
        sort: filters.sort ?? 'newest',
    });

    const applyFilters = (next: Filters) => {
        setDraft(next);

        router.get(
            browse(),
            {
                q: next.q || undefined,
                min_price: next.min_price || undefined,
                max_price: next.max_price || undefined,
                completeness:
                    next.completeness === 'all' ? undefined : next.completeness,
                stack: next.stack === 'all' ? undefined : next.stack,
                sort: next.sort === 'newest' ? undefined : next.sort,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Browse the marketplace" />

            <div className="mb-8 space-y-1">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Browse the marketplace
                </h1>
                <p className="text-muted-foreground text-sm">
                    Hand-curated projects from solo developers — {count} live
                    {count === 1 ? '' : 's'} now.
                </p>
            </div>

            <Card className="mb-6">
                <CardContent className="grid gap-4 py-4 lg:grid-cols-[1fr_auto]">
                    <form
                        className="flex flex-col gap-4 sm:flex-row"
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilters({ ...draft });
                        }}
                    >
                        <div className="relative grow">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                className="pl-9"
                                placeholder="Search title, tagline, description..."
                                value={draft.q}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        q: event.target.value,
                                    })
                                }
                            />
                        </div>

                        <div className="flex gap-2">
                            <Input
                                type="number"
                                min={0}
                                placeholder="Min $"
                                value={draft.min_price}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        min_price: event.target.value,
                                    })
                                }
                            />
                            <Input
                                type="number"
                                min={0}
                                placeholder="Max $"
                                value={draft.max_price}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        max_price: event.target.value,
                                    })
                                }
                            />
                            <Button type="submit">Apply</Button>
                        </div>
                    </form>
                </CardContent>

                <CardContent className="flex flex-wrap items-end gap-4 border-t py-4">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="filter-completeness"
                            className="text-xs"
                        >
                            Completeness
                        </Label>
                        <Select
                            value={draft.completeness}
                            onValueChange={(value) =>
                                applyFilters({ ...draft, completeness: value })
                            }
                        >
                            <SelectTrigger
                                id="filter-completeness"
                                className="w-44"
                            >
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                {completenessOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-stack" className="text-xs">
                            Tech stack
                        </Label>
                        <Select
                            value={draft.stack}
                            onValueChange={(value) =>
                                applyFilters({ ...draft, stack: value })
                            }
                        >
                            <SelectTrigger id="filter-stack" className="w-44">
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                {stacks.map((stack) => (
                                    <SelectItem key={stack} value={stack}>
                                        {stack}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-sort" className="text-xs">
                            Sort
                        </Label>
                        <Select
                            value={draft.sort}
                            onValueChange={(value) =>
                                applyFilters({ ...draft, sort: value })
                            }
                        >
                            <SelectTrigger id="filter-sort" className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {sortOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            {projects.length === 0 && (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <Search className="text-muted-foreground size-8" />
                        <div>
                            <p className="font-medium">No projects match</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Try clearing a filter or searching for something
                                else.
                            </p>
                        </div>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                applyFilters({
                                    q: '',
                                    completeness: 'all',
                                    stack: 'all',
                                    min_price: '',
                                    max_price: '',
                                    sort: 'newest',
                                })
                            }
                        >
                            Clear filters
                        </Button>
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {projects.map((project) => (
                    <Link
                        key={project.id}
                        href={show.url({ project: project.slug })}
                        className="group overflow-hidden rounded-lg border transition-shadow hover:shadow-md"
                    >
                        <CoverArt project={project} />
                        <div className="space-y-2 p-4">
                            <div className="flex items-start justify-between gap-2">
                                <h3 className="font-semibold group-hover:underline">
                                    {project.title}
                                </h3>
                                <p className="shrink-0 font-semibold">
                                    {formatPrice(
                                        project.price_cents,
                                        project.currency,
                                    )}
                                </p>
                            </div>
                            {project.tagline && (
                                <p className="text-muted-foreground line-clamp-2 text-sm">
                                    {project.tagline}
                                </p>
                            )}
                            <div className="flex items-center gap-2">
                                <Badge variant="outline">
                                    {completenessLabels[project.completeness]}
                                </Badge>
                                <span className="text-muted-foreground text-xs">
                                    {project.seller?.name ?? 'Seller'} ·{' '}
                                    {project.orders_count ?? 0} sale
                                    {(project.orders_count ?? 0) === 1
                                        ? ''
                                        : 's'}
                                </span>
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </>
    );
}

Browse.layout = {
    breadcrumbs: [
        {
            title: 'Browse',
            href: browse(),
        },
    ],
};
