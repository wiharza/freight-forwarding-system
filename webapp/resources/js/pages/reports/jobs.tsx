import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function ReportJobs({ jobs }: { jobs: any }) {
    return (
        <AppLayout>
            <Head title="Laporan Daftar Job" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Daftar Job (§7a)</h2>
                <div className="mt-4 space-y-2">
                    {jobs.data.map((job: any) => (
                        <div key={job.id} className="border rounded-lg p-3 text-sm flex justify-between">
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