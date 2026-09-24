import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    EnumOption,
    ProjectCompleteness,
    ProjectListing,
} from '@/types/projects';

type ProjectFormProps = {
    action: string;
    method: 'post';
    initial?: Partial<ProjectListing> & { tech_stack?: string[] | null };
    completenessOptions: EnumOption[];
    requireZip?: boolean;
    submitLabel: string;
    canSaveDraft?: boolean;
};

type Values = {
    title: string;
    tagline: string;
    price: string;
    description: string;
    completeness: ProjectCompleteness;
    techStack: string[];
};

const steps = [
    { title: 'Basics', description: 'Name, tagline and price' },
    { title: 'Details', description: 'Description, stack and completeness' },
    { title: 'Files', description: 'Source archive and cover image' },
];

export default function ProjectForm({
    action,
    method,
    initial,
    completenessOptions,
    requireZip = false,
    submitLabel,
    canSaveDraft = false,
}: ProjectFormProps) {
    const [step, setStep] = useState(0);
    const [stepError, setStepError] = useState<string | null>(null);
    const [tagInput, setTagInput] = useState('');
    const [tagError, setTagError] = useState<string | null>(null);
    const [values, setValues] = useState<Values>({
        title: initial?.title ?? '',
        tagline: initial?.tagline ?? '',
        price:
            initial?.price_cents != null
                ? String(initial.price_cents / 100)
                : '',
        description: initial?.description ?? '',
        completeness: initial?.completeness ?? 'mvp',
        techStack: initial?.tech_stack ?? [],
    });

    const set =
        <K extends keyof Values>(key: K) =>
        (value: Values[K]) => {
            setValues((current) => ({ ...current, [key]: value }));
        };

    const nextStep = () => {
        if (step === 0 && !values.title.trim()) {
            setStepError('A project title is required.');

            return;
        }

        if (step === 1 && !values.description.trim()) {
            setStepError('A short description is required.');

            return;
        }

        setStepError(null);
        setStep((current) => Math.min(current + 1, steps.length - 1));
    };

    const addTag = () => {
        const tag = tagInput.trim().toLowerCase();

        if (!tag) {
            return;
        }

        if (values.techStack.length >= 10) {
            setTagError('You can add up to 10 tags.');

            return;
        }

        if (values.techStack.includes(tag)) {
            setTagError('That tag was already added.');

            return;
        }

        setValues((current) => ({
            ...current,
            techStack: [...current.techStack, tag],
        }));
        setTagInput('');
        setTagError(null);
    };

    const removeTag = (tag: string) => {
        setValues((current) => ({
            ...current,
            techStack: current.techStack.filter((item) => item !== tag),
        }));
    };

    return (
        <Form
            action={action}
            method={method}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <ol className="flex items-center gap-2">
                        {steps.map((stepItem, index) => (
                            <li
                                key={stepItem.title}
                                className="flex min-w-0 items-center gap-2"
                            >
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                                        index === step
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : index < step
                                              ? 'border-primary/40 bg-primary/10'
                                              : 'border-border text-muted-foreground',
                                    )}
                                >
                                    {index + 1}
                                </span>
                                <div className="hidden min-w-0 sm:block">
                                    <p
                                        className={cn(
                                            'truncate text-sm font-medium',
                                            index <= step
                                                ? 'text-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {stepItem.title}
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {stepItem.description}
                                    </p>
                                </div>
                                {index < steps.length - 1 && (
                                    <span className="bg-border mx-2 h-px w-6" />
                                )}
                            </li>
                        ))}
                    </ol>

                    {step === 0 && (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    value={values.title}
                                    onChange={(event) =>
                                        set('title')(event.target.value)
                                    }
                                    placeholder="e.g. Laravel SaaS boilerplate"
                                    maxLength={120}
                                    required
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tagline">
                                    Tagline{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="tagline"
                                    name="tagline"
                                    value={values.tagline}
                                    onChange={(event) =>
                                        set('tagline')(event.target.value)
                                    }
                                    placeholder="One line about what you built"
                                    maxLength={160}
                                />
                                <InputError message={errors.tagline} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price">Price (USD)</Label>
                                <div className="relative">
                                    <span className="text-muted-foreground pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm">
                                        $
                                    </span>
                                    <Input
                                        id="price"
                                        name="price_cents"
                                        inputMode="decimal"
                                        type="text"
                                        className="pl-7"
                                        value={values.price}
                                        onChange={(event) =>
                                            set('price')(event.target.value)
                                        }
                                        placeholder="0.00"
                                        required
                                    />
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Your buyer pays this amount directly to you
                                    via Stripe Connect.
                                </p>
                                <InputError message={errors.price_cents} />
                            </div>

                            <input type="hidden" name="currency" value="USD" />
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    className="min-h-40"
                                    value={values.description}
                                    onChange={(event) =>
                                        set('description')(event.target.value)
                                    }
                                    placeholder="What does the project do, and what is included in the archive?"
                                    maxLength={5000}
                                    required
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="completeness">
                                    Completeness
                                </Label>
                                <Select
                                    name="completeness"
                                    value={values.completeness}
                                    onValueChange={(value) =>
                                        set('completeness')(
                                            value as ProjectCompleteness,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="completeness"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a stage" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                <p className="text-muted-foreground text-xs">
                                    Concept &rarr; Starter &rarr; MVP &rarr;
                                    Complete
                                </p>
                                <InputError message={errors.completeness} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Tech stack</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={tagInput}
                                        onChange={(event) =>
                                            setTagInput(event.target.value)
                                        }
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                addTag();
                                            }
                                        }}
                                        placeholder="laravel, react, docker…"
                                        maxLength={24}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addTag}
                                    >
                                        Add
                                    </Button>
                                </div>
                                {tagError && <InputError message={tagError} />}
                                {values.techStack.length > 0 && (
                                    <ul className="flex flex-wrap gap-1.5">
                                        {values.techStack.map((tag) => (
                                            <li
                                                key={tag}
                                                className="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs"
                                            >
                                                <span className="font-mono">
                                                    {tag}
                                                </span>
                                                <button
                                                    type="button"
                                                    className="hover:text-foreground text-sm leading-none"
                                                    onClick={() =>
                                                        removeTag(tag)
                                                    }
                                                    aria-label={`Remove ${tag}`}
                                                >
                                                    ×
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {values.techStack.map((tag) => (
                                    <input
                                        key={tag}
                                        type="hidden"
                                        name="tech_stack[]"
                                        value={tag}
                                    />
                                ))}
                                <InputError message={errors.tech_stack} />
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="zip">
                                    Source archive{' '}
                                    <span className="text-muted-foreground">
                                        (.zip, up to 50 MB)
                                    </span>
                                </Label>
                                <Input
                                    id="zip"
                                    name="zip"
                                    type="file"
                                    accept=".zip,application/zip"
                                    required={requireZip}
                                />
                                <p className="text-muted-foreground text-xs">
                                    The archive stays private until your project
                                    is approved.
                                </p>
                                <InputError message={errors.zip} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cover_image">
                                    Cover image{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="cover_image"
                                    name="cover_image"
                                    type="file"
                                    accept="image/*"
                                />
                                <InputError message={errors.cover_image} />
                            </div>
                        </div>
                    )}

                    {stepError && <InputError message={stepError} />}

                    <div className="flex items-center justify-between gap-4 border-t pt-4">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setStep((current) => current - 1)}
                            disabled={step === 0}
                        >
                            Back
                        </Button>

                        {step < steps.length - 1 ? (
                            <Button type="button" onClick={nextStep}>
                                Continue
                            </Button>
                        ) : (
                            <div className="flex items-center gap-3">
                                {canSaveDraft && (
                                    <Button
                                        type="submit"
                                        name="save"
                                        value="draft"
                                        variant="outline"
                                        className="text-muted-foreground"
                                    >
                                        Save as draft
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    name={canSaveDraft ? 'save' : undefined}
                                    value={canSaveDraft ? 'submit' : undefined}
                                    disabled={processing}
                                >
                                    {submitLabel}
                                </Button>
                            </div>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
