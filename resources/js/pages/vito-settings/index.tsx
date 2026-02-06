import AdminLayout from '@/layouts/admin/layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import Heading from '@/components/heading';
import { Card, CardContent, CardRow } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import ExportVito from '@/pages/vito-settings/components/export';
import ImportVito from '@/pages/vito-settings/components/import';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { route } from 'ziggy-js';

export default function Users() {
  return (
    <AdminLayout>
      <Head title="System Settings" />

      <Container className="">
        <div className="flex items-start justify-between">
          <Heading title="System Settings" description="Here you can manage general system settings" />
        </div>

        <Card>
          <CardContent>
            <CardRow>
              <span>Export all data</span>
              <ExportVito />
            </CardRow>
            <Separator />
            <CardRow>
              <span>Import</span>
              <ImportVito />
            </CardRow>
            <Separator />
            <CardRow>
                <div>
                  <h3 className="text-lg font-medium text-foreground">General Settings</h3>
                  <p className="text-sm text-muted-foreground mb-4">Update your application name and branding.</p>
                  <GeneralSettingsForm />
                </div>
            </CardRow>
          </CardContent>
        </Card>
      </Container>
    </AdminLayout>
  );
}

import { SharedData } from '@/types';

function GeneralSettingsForm() {
    const { name, logo, favicon } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors } = useForm({
        app_name: name || '',
        logo: null as File | null,
        favicon: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('vito-settings.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4 max-w-xl">
            <div>
                <Label htmlFor="app_name">App Name</Label>
                <Input
                    id="app_name"
                    value={data.app_name}
                    onChange={e => setData('app_name', e.target.value)}
                    className="mt-1"
                />
                <InputError message={errors.app_name} className="mt-2" />
            </div>

            <div>
                <Label htmlFor="logo">Logo</Label>
               {logo && <img src={logo} alt="Current Logo" className="h-10 mb-2 object-contain" />}
                <Input
                    id="logo"
                    type="file"
                    onChange={e => setData('logo', e.target.files ? e.target.files[0] : null)}
                    className="mt-1"
                    accept="image/*"
                />
                <InputError message={errors.logo} className="mt-2" />
            </div>

            <div>
                <Label htmlFor="favicon">Favicon</Label>
                {favicon && <img src={favicon} alt="Current Favicon" className="h-8 w-8 mb-2 object-contain" />}
                <Input
                    id="favicon"
                    type="file"
                    onChange={e => setData('favicon', e.target.files ? e.target.files[0] : null)}
                    className="mt-1"
                    accept="image/*"
                />
                <InputError message={errors.favicon} className="mt-2" />
            </div>

            <Button disabled={processing}>Save Changes</Button>
        </form>
    );
}



