import { ColumnDef } from '@tanstack/react-table';
import { Server } from '@/types/server';
import { Link, useForm } from '@inertiajs/react';
import DateTime from '@/components/date-time';
import { Site } from '@/types/site';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EyeIcon, RefreshCcwIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function getColumns(server?: Server): ColumnDef<Site>[] {
  let columns: ColumnDef<Site>[] = [
    {
      accessorKey: 'id',
      header: 'ID',
      enableColumnFilter: true,
      enableSorting: true,
      enableHiding: true,
    },
    {
      accessorKey: 'domain',
      header: 'Domain',
      enableColumnFilter: true,
      enableSorting: true,
    },
    {
      accessorKey: 'type',
      header: 'Type',
      enableColumnFilter: true,
      enableSorting: true,
      cell: ({ row }) => {
        return <Badge variant="outline">{row.original.type}</Badge>;
      },
    },
    {
      accessorKey: 'created_at',
      header: 'Created at',
      enableColumnFilter: true,
      enableSorting: true,
      cell: ({ row }) => {
        return <DateTime date={row.original.created_at} />;
      },
    },
    {
      accessorKey: 'status',
      header: 'Status',
      enableColumnFilter: true,
      enableSorting: true,
      cell: ({ row }) => {
        const site = row.original;
        const form = useForm();
        const rebuild = () => {
          form.post(route('sites.rebuild', { server: site.server_id, site: site.id }));
        };

        return (
          <div className="flex items-center space-x-1">
            <Badge variant={site.status_color}>{site.status}</Badge>
            {site.status === 'installation_failed' && (
              <Button variant="ghost" size="icon" className="h-4 w-4" onClick={rebuild} disabled={form.processing}>
                <RefreshCcwIcon className={cn('h-3 w-3', form.processing ? 'animate-spin' : '')} />
              </Button>
            )}
          </div>
        );
      },
    },
    {
      id: 'actions',
      enableColumnFilter: false,
      enableSorting: false,
      cell: ({ row }) => {
        return (
          <div className="flex items-center justify-end">
            <Link href={route('application', { server: row.original.server_id, site: row.original.id })} prefetch>
              <Button variant="outline" size="sm">
                <EyeIcon />
              </Button>
            </Link>
          </div>
        );
      },
    },
  ];

  if (!server) {
    // add column to the first
    columns = [
      {
        id: 'server',
        header: 'Server',
        cell: ({ row }) => {
          return (
            <Link href={route('servers.show', { server: row.original.server_id })} prefetch>
              {row.original.server?.name}
            </Link>
          );
        },
      },
      ...columns,
    ];
  }

  return columns;
}
