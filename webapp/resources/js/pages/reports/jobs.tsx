import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type ReportJob = {
    id: number;
    job_number: string;
    importir?: { name: string } | null;
    cached_status: string;
};

export default function ReportJobs({ jobs }: { jobs: { data: ReportJob[] } }) {
    return (
        <AppLayout>
            <Head title="Laporan Daftar Job" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Daftar Job (§7a)</h2>
                <div className="mt-4 space-y-2">
                    {jobs.data.map((job) => (
                        <div key={job.id} className="flex justify-between rounded-lg border p-3 text-sm">
                            <span className="font-mono font-bold">{job.job_number}</span>
                            <span>{job.importir?.name}</span>
                            <span className="font-medium">{job.cached_status}</span>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
