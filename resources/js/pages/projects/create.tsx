import { Head } from '@inertiajs/react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import ProjectForm from '@/components/project-form';
import Heading from '@/components/heading';
import { create, mine } from '@/routes/projects';
import type { EnumOption } from '@/types/projects';

type CreateProps = {
    completenessOptions: EnumOption[];
};

export default function CreateProject({ completenessOptions }: CreateProps) {
    const form = ProjectController.store.form();

    return (
        <>
            <Head title="Upload a project" />

            <Heading
                title="Upload a project"
                description="Describe your project, set a price, and attach the source archive"
            />

            <ProjectForm
                action={form.action}
                method="post"
                completenessOptions={completenessOptions}
                requireZip
                submitLabel="Submit for review"
                canSaveDraft
            />
        </>
    );
}

CreateProject.layout = {
    breadcrumbs: [
        {
            title: 'My Projects',
            href: mine(),
        },
        {
            title: 'Upload',
            href: create(),
        },
    ],
};
