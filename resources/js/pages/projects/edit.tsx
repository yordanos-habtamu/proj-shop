import { Head } from '@inertiajs/react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import ProjectForm from '@/components/project-form';
import Heading from '@/components/heading';
import { edit, mine } from '@/routes/projects';
import {
    statusLabels,
    type EnumOption,
    type ProjectListing,
} from '@/types/projects';

type EditProps = {
    project: ProjectListing;
    completenessOptions: EnumOption[];
};

export default function EditProject({
    project,
    completenessOptions,
}: EditProps) {
    const form = ProjectController.update.form({ project: project.id });

    return (
        <>
            <Head title={`Edit ${project.title}`} />

            <Heading
                title={`Edit ${project.title}`}
                description={`Status: ${statusLabels[project.status]}. Changes to the archive trigger a fresh review.`}
            />

            <ProjectForm
                action={form.action}
                method="post"
                initial={project}
                completenessOptions={completenessOptions}
                submitLabel="Save changes"
            />
        </>
    );
}

EditProject.layout = {
    breadcrumbs: [
        {
            title: 'My Projects',
            href: mine(),
        },
        {
            title: 'Edit',
            href: edit.url({ project: 1 }),
        },
    ],
};
