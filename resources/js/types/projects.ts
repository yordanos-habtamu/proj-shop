export type ProjectCompleteness = 'concept' | 'starter' | 'mvp' | 'complete';

export type ProjectStatus =
    | 'draft'
    | 'pending_review'
    | 'approved'
    | 'rejected'
    | 'delisted';

export type EnumOption = {
    value: string;
    label: string;
};

export type ProjectListing = {
    id: number;
    seller_id: number;
    title: string;
    slug: string;
    tagline: string | null;
    description: string;
    price_cents: number;
    currency: string;
    completeness: ProjectCompleteness;
    status: ProjectStatus;
    cover_image_path: string | null;
    tech_stack: string[] | null;
    review_notes: string | null;
    reviewed_at: string | null;
    orders_count?: number;
    created_at: string;
    updated_at: string;
};

export const completenessLabels: Record<ProjectCompleteness, string> = {
    concept: 'Concept',
    starter: 'Starter',
    mvp: 'MVP',
    complete: 'Complete',
};

export const statusLabels: Record<ProjectStatus, string> = {
    draft: 'Draft',
    pending_review: 'Pending review',
    approved: 'Approved',
    rejected: 'Rejected',
    delisted: 'Delisted',
};

export function formatPrice(cents: number, currency = 'USD'): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(cents / 100);
}
