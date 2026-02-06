import axios from 'axios';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { ReactNode, useState, FormEventHandler, useEffect } from 'react';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { LoaderCircle, HelpCircle } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useForm, usePage } from '@inertiajs/react';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/ui/input-error';
import type { SharedData } from '@/types';
import SourceControlSelect from '@/pages/source-controls/components/source-control-select';
import { Server } from '@/types/server';
import ServerSelect from '@/pages/servers/components/server-select';
import ServiceVersionSelect from '@/pages/services/components/service-version-select';
import { DynamicFieldConfig } from '@/types/dynamic-field-config';
import DynamicField from '@/components/ui/dynamic-field';
import { TagsInput } from '@/components/ui/tags-input';
import DatabaseSelect from '@/pages/databases/components/database-select';
import DatabaseUserSelect from '@/pages/database-users/components/database-user-select';
import SelectRepo from '@/pages/source-controls/components/select-repo';
import SelectBranch from '@/pages/source-controls/components/select-branch';
import { Textarea } from '@/components/ui/textarea';
import DomainSelect from '@/pages/domains/components/domain-select';

const MANUAL_FIELDS = ['source_control', 'repository', 'branch', 'php_version', 'web_directory', 'nodejs_version', 'python_version', 'go_version', 'build_command', 'start_command', 'install_command'];

type CreateSiteForm = {
  server: string;
  type: string;
  domain: string;
  subdomain_prefix: string;
  selected_domain_id: string;
  use_custom_domain: boolean;
  aliases: string[];
  php_version: string;
  source_control: string;
  repository: string;
  branch: string;
  user: string;
  nodejs_version: string;
  python_version: string;
  go_version: string;
  env: string;
};

export default function CreateSite({
  server,
  defaultOpen,
  onOpenChange,
  children,
}: {
  server?: Server;
  defaultOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
  children: ReactNode;
}) {
  const page = usePage<SharedData>();
  const [open, setOpen] = useState(defaultOpen || false);
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [sourceType, setSourceType] = useState<'git' | 'zip'>('git');
  const [envSuggestions, setEnvSuggestions] = useState<string[]>([]);
  const [envValues, setEnvValues] = useState<string>('');
  const [isUserTouched, setIsUserTouched] = useState(false);
  const [selectedDomainName, setSelectedDomainName] = useState<string>('');

  useEffect(() => {
    if (defaultOpen !== undefined) {
      setOpen(defaultOpen);
    }
  }, [defaultOpen]);

  const handleOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
    if (onOpenChange) {
      onOpenChange(isOpen);
    }
  };

  const form = useForm<CreateSiteForm>({
    server: server?.id.toString() || '',
    type: 'php',
    domain: '',
    subdomain_prefix: '',
    selected_domain_id: '',
    use_custom_domain: false,
    aliases: [],
    php_version: '',
    source_control: '',
    repository: '',
    branch: '',
    user: '',
    nodejs_version: '',
    python_version: '',
    go_version: '',
    env: '',
  });

  /* eslint-disable @typescript-eslint/no-explicit-any */
  const applyAnalysis = (result: any) => {
      if (result.type && page.props.configs.site.types[result.type]) {
          form.setData('type', result.type);
      }
      if (result.php_version) form.setData('php_version', result.php_version);
      if (result.node_version) form.setData('nodejs_version', result.node_version);
      // Python/Go/Static might not have version detection implemented in detectors yet or DTO doesn't support it fully
      // But if we did:
      // if (result.python_version) form.setData('python_version', result.python_version);
      
      if (result.env_suggestions) {
          setEnvSuggestions(result.env_suggestions);
          setEnvValues(result.env_suggestions.map((k: string) => `${k}=`).join('\n'));
      }
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.setData('env', envValues);
    
    // For Zip uploads, exclude Git-related fields to avoid validation errors
    if (sourceType === 'zip') {
      const gitFields = ['source_control', 'repository', 'branch'];
      const dataWithoutGit = Object.fromEntries(
        Object.entries(form.data).filter(([key]) => !gitFields.includes(key))
      );
      /* @ts-expect-error dynamic types */
      if (form.data.zip_file) {
          /* @ts-expect-error dynamic types */
          dataWithoutGit['zip_file'] = form.data.zip_file;
      }
      form.transform(() => dataWithoutGit);
    }
    
    form.post(route('sites.store', { server: form.data.server }), {
        forceFormData: true,
    });
  };

  const runAnalysis = async (repo: string, branch: string) => {
      setIsAnalyzing(true);
      try {
          const res = await axios.post(route('api.analysis.git'), {
              source_control_id: form.data.source_control,
              repository: repo,
              branch: branch
          });
          applyAnalysis(res.data);
      } catch (e) {
          console.error(e);
      } finally {
          setIsAnalyzing(false);
      }
  };

  const runZipAnalysis = async (file: File) => {
      setIsAnalyzing(true);
      /* @ts-expect-error dynamic types */
      form.setData('zip_file', file);
      
      const formData = new FormData();
      formData.append('file', file);
      try {
          const res = await axios.post(route('api.analysis.zip'), formData, {
              headers: {
                  'Content-Type': 'multipart/form-data'
              }
          });
          applyAnalysis(res.data);
      } catch (e) {
          console.error(e);
      } finally {
          setIsAnalyzing(false);
      }
  };

  useEffect(() => {
    const typeConfig = page.props.configs.site.types[form.data.type];

    if (typeConfig?.form) {
      typeConfig.form.forEach((field: DynamicFieldConfig) => {
        if (field.default !== undefined) {
          /* @ts-expect-error dynamic types */
          if (form.data[field.name] === '' || form.data[field.name] === undefined) {
            /* @ts-expect-error dynamic types */
            form.setData(field.name, field.default);
          }
        }
      });
      }
    }, [form.data.type]);


    const slugify = (text: string) => {
        return text
            .toString()
            .toLowerCase()
            .replace(/\./g, '_')            // Replace dots with _
            .replace(/\s+/g, '_')           // Replace spaces with _
            .replace(/[^\w-]+/g, '')        // Remove all non-word chars
            .replace(/--+/g, '_')           // Replace multiple - with single _
            .replace(/__+/g, '_')           // Replace multiple _ with single _
            .replace(/^-+/, '')             // Trim - from start
            .replace(/-+$/, '')             // Trim - from end
            .substring(0, 32);
    };

    useEffect(() => {
        if (form.data.domain && !isUserTouched) {
            const slug = slugify(form.data.domain);
            form.setData('user', slug);
        }
    }, [form.data.domain, isUserTouched]);

  const getFormField = (field: DynamicFieldConfig) => {
    if (field.name === 'source_control') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="source_control">Source Control</Label>
          <SourceControlSelect
            id="source_control"
            value={form.data.source_control}
            onValueChange={(value) => form.setData('source_control', value)}
          />
          <InputError message={form.errors.source_control} />
        </FormField>
      );
    }

    if (field.name === 'repository') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="repository">Repository</Label>
          <SelectRepo
            sourceControlId={form.data.source_control}
            value={form.data.repository}
            onValueChange={(value) => form.setData('repository', value)}
            placeholder="owner/repository"
          />
          <InputError message={form.errors.repository} />
        </FormField>
      );
    }

    if (field.name === 'branch') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="branch">Branch</Label>
          <SelectBranch
            sourceControlId={form.data.source_control}
            repository={form.data.repository}
            value={form.data.branch}
            onValueChange={(value) => form.setData('branch', value)}
            placeholder="e.g. main, master, develop"
          />
          <InputError message={form.errors.branch} />
        </FormField>
      );
    }

    if (field.name === 'php_version') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="php_version">PHP Version</Label>
          <ServiceVersionSelect
            id="php_version"
            serverId={parseInt(form.data.server)}
            service="php"
            value={form.data.php_version}
            onValueChange={(value) => form.setData('php_version', value)}
          />
          <InputError message={form.errors.php_version} />
        </FormField>
      );
    }

    if (field.name === 'nodejs_version') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="nodejs_version">Node.js Version</Label>
          <ServiceVersionSelect
            id="nodejs_version"
            serverId={parseInt(form.data.server)}
            service="nodejs"
            value={form.data.nodejs_version}
            onValueChange={(value) => form.setData('nodejs_version', value)}
          />
          <InputError message={form.errors.nodejs_version} />
        </FormField>
      );
    }

    if (field.name === 'python_version') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="python_version">Python Version</Label>
          <ServiceVersionSelect
            id="python_version"
            serverId={parseInt(form.data.server)}
            service="python"
            value={form.data.python_version}
            onValueChange={(value) => form.setData('python_version', value)}
          />
          <InputError message={form.errors.python_version} />
        </FormField>
      );
    }

    if (field.name === 'go_version') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="go_version">Go Version</Label>
          <ServiceVersionSelect
            id="go_version"
            serverId={parseInt(form.data.server)}
            service="go"
            value={form.data.go_version}
            onValueChange={(value) => form.setData('go_version', value)}
          />
          <InputError message={form.errors.go_version} />
        </FormField>
      );
    }

    if (field.name === 'database') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="database">Database</Label>
          <DatabaseSelect
            id="database"
            key={`field-${field.name}`}
            name="database"
            serverId={parseInt(form.data.server)}
            /*@ts-expect-error dynamic types*/
            value={form.data.database}
            /*@ts-expect-error dynamic types*/
            onValueChange={(value) => form.setData('database', value)}
            createWithUser={true}
            defaultCharset={field.componentProps?.defaultCharset as string | undefined}
            defaultCollation={field.componentProps?.defaultCollation as string | undefined}
          />
          {/*@ts-expect-error dynamic types*/}
          <InputError message={form.errors.database} />
        </FormField>
      );
    }

    if (field.name === 'database_user') {
      return (
        <FormField key={`field-${field.name}`}>
          <Label htmlFor="database-user">Database user</Label>
          <DatabaseUserSelect
            id="database-user"
            key={`field-${field.name}`}
            name="database_user"
            serverId={parseInt(form.data.server)}
            /*@ts-expect-error dynamic types*/
            value={form.data.database_user}
            /*@ts-expect-error dynamic types*/
            onValueChange={(value) => form.setData('database_user', value)}
            create={false}
          />
          {/*@ts-expect-error dynamic types*/}
          <InputError message={form.errors.database_user} />
        </FormField>
      );
    }

    return (
      <DynamicField
        key={`field-${field.name}`}
        /*@ts-expect-error dynamic types*/
        value={form.data[field.name]}
        /*@ts-expect-error dynamic types*/
        onChange={(value) => form.setData(field.name, value)}
        config={field}
        /*@ts-expect-error dynamic types*/
        error={form.errors[field.name]}
      />
    );
  };

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetTrigger asChild>{children}</SheetTrigger>
      <SheetContent className="w-full md:max-w-5xl lg:max-w-6xl">
        <SheetHeader>
          <SheetTitle>Create site</SheetTitle>
          <SheetDescription>Fill in the details to create a new site.</SheetDescription>
        </SheetHeader>
        <Form id="create-site-form" className="p-4" onSubmit={submit}>
          <FormFields>
            {server === undefined && (
              <FormField>
                <Label htmlFor="server">Server</Label>
                <ServerSelect value={form.data.server} onValueChange={(value) => form.setData('server', value ? value.id.toString() : '')} />
                <InputError message={form.errors.server} />
              </FormField>
            )}

            {form.data.server && (
              <>
                 <div className="space-y-4 mb-6 border p-4 rounded-md bg-muted/20">
                     <h3 className="font-semibold mb-2">Source Code</h3>
                     <div className="flex gap-4 mb-4">
                         <Button type="button" variant={sourceType === 'git' ? 'default' : 'outline'} onClick={() => setSourceType('git')}>Git Repository</Button>
                         <Button type="button" variant={sourceType === 'zip' ? 'default' : 'outline'} onClick={() => setSourceType('zip')}>Upload Zip</Button>
                     </div>

                     {sourceType === 'git' && (
                         <>
                            <FormField>
                                <Label htmlFor="source_control">Source Control Provider</Label>
                                <SourceControlSelect
                                    id="source_control"
                                    value={form.data.source_control}
                                    onValueChange={(value) => form.setData('source_control', value)}
                                />
                                <InputError message={form.errors.source_control} />
                            </FormField>
                            {form.data.source_control && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <FormField>
                                        <Label htmlFor="repository">Repository</Label>
                                        <SelectRepo
                                            sourceControlId={form.data.source_control}
                                            value={form.data.repository}
                                            onValueChange={(value) => form.setData('repository', value)}
                                            placeholder="owner/repository"
                                        />
                                        <InputError message={form.errors.repository} />
                                    </FormField>
                                    <FormField>
                                        <Label htmlFor="branch">Branch</Label>
                                        <SelectBranch
                                            sourceControlId={form.data.source_control}
                                            repository={form.data.repository}
                                            value={form.data.branch}
                                            onValueChange={(value) => {
                                                form.setData('branch', value);
                                                runAnalysis(form.data.repository, value);
                                            }}
                                            placeholder="main"
                                        />
                                        <InputError message={form.errors.branch} />
                                    </FormField>
                                </div>
                            )}
                         </>
                     )}

                     {sourceType === 'zip' && (
                         <FormField>
                             <Label>Zip File</Label>
                             <Input type="file" accept=".zip" onChange={(e) => {
                                 if (e.target.files?.[0]) {
                                     runZipAnalysis(e.target.files[0]);
                                 }
                             }} />
                             <p className="text-xs text-muted-foreground mt-1">Upload a zip file of your project.</p>
                         </FormField>
                     )}

                     {isAnalyzing && (
                         <div className="flex items-center gap-2 text-primary animate-pulse">
                             <LoaderCircle className="h-4 w-4 animate-spin" /> Analyzing project structure...
                         </div>
                     )}
                 </div>
              </>
            )}

            {form.data.server && (
              <>
                <FormField>
                  <Label htmlFor="type">Site Type</Label>
                  <Select value={form.data.type} onValueChange={(value) => form.setData('type', value)}>
                    <SelectTrigger id="type">
                      <SelectValue placeholder="Select site type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectGroup>
                        {Object.entries(page.props.configs.site.types).map(([key, type]) => (
                          <SelectItem key={`type-${key}`} value={key}>
                            {type.label}
                          </SelectItem>
                        ))}
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                  <InputError message={form.errors.type} />
                </FormField>

                <FormField>
                  <Label>Domain</Label>
                  <div className="space-y-3">
                    {/* Option 1: Use Registered Domain with Subdomain */}
                    <div className="flex gap-2 items-end">
                      <div className="flex-1">
                        <Label htmlFor="subdomain" className="text-xs text-muted-foreground">Subdomain (optional)</Label>
                        <Input
                          id="subdomain"
                          placeholder="e.g., app, api, www"
                          value={form.data.subdomain_prefix}
                          onChange={(e) => {
                            form.setData('subdomain_prefix', e.target.value);
                            if (!form.data.use_custom_domain && selectedDomainName) {
                              const computed = e.target.value ? `${e.target.value}.${selectedDomainName}` : selectedDomainName;
                              form.setData('domain', computed);
                            }
                          }}
                          disabled={form.data.use_custom_domain}
                        />
                      </div>
                      <span className="text-muted-foreground pb-2">.</span>
                      <div className="flex-[2]">
                        <Label htmlFor="domain-select" className="text-xs text-muted-foreground">Registered Domain</Label>
                        <DomainSelect
                          id="domain-select"
                          value={form.data.selected_domain_id}
                          onValueChange={(domainId, domainName) => {
                            form.setData('selected_domain_id', domainId);
                            form.setData('use_custom_domain', false);
                            setSelectedDomainName(domainName);
                            const computed = form.data.subdomain_prefix ? `${form.data.subdomain_prefix}.${domainName}` : domainName;
                            form.setData('domain', computed);
                          }}
                        />
                      </div>
                    </div>

                    {/* OR Separator */}
                    <div className="flex items-center gap-2">
                      <div className="flex-1 border-t" />
                      <span className="text-xs text-muted-foreground">OR</span>
                      <div className="flex-1 border-t" />
                    </div>

                    {/* Option 2: Custom Domain */}
                    <div>
                      <Label htmlFor="custom-domain" className="text-xs text-muted-foreground">Custom Domain</Label>
                      <Input
                        id="custom-domain"
                        placeholder="your-domain.com"
                        value={form.data.use_custom_domain ? form.data.domain : ''}
                        onChange={(e) => {
                          form.setData('use_custom_domain', true);
                          form.setData('domain', e.target.value);
                          form.setData('selected_domain_id', '');
                          form.setData('subdomain_prefix', '');
                        }}
                      />
                    </div>
                  </div>
                  <InputError message={form.errors.domain} />
                </FormField>

                <FormField>
                  <Label htmlFor="aliases">Aliases</Label>
                  <TagsInput
                    id="aliases"
                    type="text"
                    value={form.data.aliases}
                    placeholder="Add aliases"
                    onValueChange={(value) => form.setData('aliases', value)}
                  />
                  <p className="text-muted-foreground text-xs">Press enter or comma to add an alias and press backspace to remove the last alias.</p>
                  <InputError message={form.errors.aliases} />
                  {Object.keys(form.errors)
                    .filter((key) => key.startsWith('aliases.'))
                    .map((key) => (
                      <InputError key={key} message={form.errors[key as keyof typeof form.errors] as string} />
                    ))}
                </FormField>

                <FormField>
                  <Label htmlFor="user" className="flex items-center gap-1">
                    Isolated User
                    <Dialog>
                      <TooltipProvider>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <DialogTrigger asChild>
                              <button type="button" className="text-muted-foreground hover:text-foreground">
                                <HelpCircle className="h-4 w-4" />
                              </button>
                            </DialogTrigger>
                          </TooltipTrigger>
                          <TooltipContent>Why?</TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                      <DialogContent>
                        <DialogHeader>
                          <DialogTitle>Why Isolated Users?</DialogTitle>
                          <DialogDescription>
                            Isolated users are mandatory to ensure security for your sites. If a site has security vulnerabilities and gets
                            compromised, the attacker cannot take full control of the server because the site runs under its own isolated user with
                            limited permissions.
                          </DialogDescription>
                        </DialogHeader>
                      </DialogContent>
                    </Dialog>
                  </Label>
                  <Input
                    id="user"
                    type="text"
                    value={form.data.user}
                    onChange={(e) => {
                        form.setData('user', e.target.value);
                        setIsUserTouched(true);
                    }}
                    placeholder="e.g. mysite"
                  />
                  <p className="text-muted-foreground text-xs">The isolated user for the site. Must be unique on the server.</p>
                  <InputError message={form.errors.user} />
                </FormField>

                {/* Detectable Configs Section */}
                <div className="space-y-4 border-t pt-4">
                    <h3 className="font-semibold text-sm text-foreground/70">Configuration</h3>
                    {page.props.configs.site.types[form.data.type].form?.filter(f => !MANUAL_FIELDS.includes(f.name)).map((config) => getFormField(config))}
                    
                    {/* Render Manual Fields if they exist in config */}
                    {page.props.configs.site.types[form.data.type].form?.find(f => f.name === 'php_version') && (
                       <FormField>
                         <Label htmlFor="php_version">PHP Version</Label>
                         <ServiceVersionSelect
                           id="php_version"
                           serverId={parseInt(form.data.server)}
                           service="php"
                           value={form.data.php_version}
                           onValueChange={(value) => form.setData('php_version', value)}
                         />
                         <InputError message={form.errors.php_version} />
                       </FormField>
                    )}

                    {page.props.configs.site.types[form.data.type].form?.find(f => f.name === 'nodejs_version') && (
                       <FormField>
                         <Label htmlFor="nodejs_version">Node.js Version</Label>
                         <ServiceVersionSelect
                           id="nodejs_version"
                           serverId={parseInt(form.data.server)}
                           service="nodejs"
                           value={form.data.nodejs_version}
                           onValueChange={(value) => form.setData('nodejs_version', value)}
                         />
                         <InputError message={form.errors.nodejs_version} />
                       </FormField>
                    )}

                    {page.props.configs.site.types[form.data.type].form?.find(f => f.name === 'python_version') && (
                       <FormField>
                         <Label htmlFor="python_version">Python Version</Label>
                         <ServiceVersionSelect
                           id="python_version"
                           serverId={parseInt(form.data.server)}
                           service="python"
                           value={form.data.python_version}
                           onValueChange={(value) => form.setData('python_version', value)}
                         />
                         <InputError message={form.errors.python_version} />
                       </FormField>
                    )}

                    {page.props.configs.site.types[form.data.type].form?.find(f => f.name === 'go_version') && (
                       <FormField>
                         <Label htmlFor="go_version">Go Version</Label>
                         <ServiceVersionSelect
                           id="go_version"
                           serverId={parseInt(form.data.server)}
                           service="go"
                           value={form.data.go_version}
                           onValueChange={(value) => form.setData('go_version', value)}
                         />
                         <InputError message={form.errors.go_version} />
                       </FormField>
                    )}
                </div>

                {envSuggestions.length > 0 && (
                    <div className="space-y-2 border-t pt-4">
                        <Label>Environment Variables (Detected)</Label>
                        <Textarea 
                            rows={5} 
                            value={envValues} 
                            onChange={(e) => setEnvValues(e.target.value)} 
                            placeholder="KEY=VALUE"
                            className="font-mono text-sm"
                        />
                        <p className="text-xs text-muted-foreground">Adjust the values for your detected environment variables.</p>
                    </div>
                )}
              </>
            )}
          </FormFields>
        </Form>
        <SheetFooter>
          <div className="flex items-center gap-2">
            <Button type="submit" form="create-site-form" disabled={form.processing || !form.data.server}>
              {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />} Create
            </Button>
            <SheetClose asChild>
              <Button variant="outline" disabled={form.processing}>
                Cancel
              </Button>
            </SheetClose>
          </div>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
