import React, { useState, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import ServerLayout from '@/layouts/server/layout';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Loader2,
    FileIcon,
    FolderIcon,
    CornerLeftUpIcon,
    SaveIcon,
    RefreshCwIcon
} from 'lucide-react';
import { Server } from '@/types/server';
import { Site } from '@/types/site';
import axios from 'axios';
import Editor from '@monaco-editor/react';
import { toast } from 'sonner';

type FileItem = {
    name: string;
    path: string;
    is_dir: boolean;
    size: number;
    last_modified: number;
    permissions: string;
};

type PageProps = {
    server: Server;
    site: Site;
};

export default function SiteFiles() {
    const { props } = usePage<PageProps>();
    const { server, site } = props;

    // State
    const [currentPath, setCurrentPath] = useState('/');
    const [files, setFiles] = useState<FileItem[]>([]);
    const [isLoadingFiles, setIsLoadingFiles] = useState(false);
    
    // Editor State
    const [selectedFile, setSelectedFile] = useState<FileItem | null>(null);
    const [fileContent, setFileContent] = useState('');
    const [isLoadingContent, setIsLoadingContent] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    // Initial Load
    useEffect(() => {
        loadFiles(currentPath);
    }, [currentPath]);

    const loadFiles = async (path: string) => {
        setIsLoadingFiles(true);
        try {
            const res = await axios.get(route('sites.files.list', { server: server.id, site: site.id }), {
                params: { path }
            });
            setFiles(res.data);
        } catch (error) {
            toast.error('Failed to load files');
            console.error(error);
        } finally {
            setIsLoadingFiles(false);
        }
    };

    const handleFileClick = async (file: FileItem) => {
        if (file.is_dir) {
            // Navigate into directory
            // Ensure path starts with / and ends with / logic
            // Use newPath logic to ensure consistency
            const newPath = file.path.startsWith('/') ? file.path : '/' + file.path;
            setCurrentPath(newPath); // Fixed: Use newPath
        } else {
            // Open file
            setSelectedFile(file);
            loadFileContent(file.path);
        }
    };

    const loadFileContent = async (path: string) => {
        setIsLoadingContent(true);
        setFileContent(''); // Clear previous content
        try {
            const res = await axios.get(route('sites.files.show', { server: server.id, site: site.id }), {
                params: { path }
            });
            setFileContent(res.data.content);
        } catch {
            toast.error('Failed to load file content');
        } finally {
            setIsLoadingContent(false);
        }
    };

    const handleSave = async () => {
        if (!selectedFile) return;
        
        setIsSaving(true);
        try {
            await axios.put(route('sites.files.update', { server: server.id, site: site.id }), {
                path: selectedFile.path,
                content: fileContent
            });
            toast.success('File saved successfully');
        } catch {
            toast.error('Failed to save file');
        } finally {
            setIsSaving(false);
        }
    };

    const handleGoUp = () => {
        if (currentPath === '/' || currentPath === '') return;
        const parts = currentPath.split('/').filter(p => p);
        parts.pop();
        const newPath = '/' + parts.join('/');
        setCurrentPath(newPath);
    };

    const determineLanguage = (filename: string) => {
        const ext = filename.split('.').pop()?.toLowerCase();
        switch (ext) {
            case 'php': return 'php';
            case 'js': return 'javascript';
            case 'ts': return 'typescript';
            case 'tsx': return 'typescript';
            case 'jsx': return 'javascript';
            case 'css': return 'css';
            case 'html': return 'html';
            case 'json': return 'json';
            case 'env': return 'shell'; // Monaco doesn't have .env specific, shell or plaintext
            case 'yml':
            case 'yaml': return 'yaml';
            case 'xml': return 'xml';
            case 'sql': return 'sql';
            case 'md': return 'markdown';
            default: return 'plaintext';
        }
    };

    return (
        <ServerLayout>
            <Head title={`Files - ${site.domain}`} />
            
            <Container>
                <HeaderContainer>
                    <Heading title="File Manager" description={`Manage files for ${site.domain}`} />
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => loadFiles(currentPath)} disabled={isLoadingFiles}>
                            <RefreshCwIcon className={`w-4 h-4 mr-2 ${isLoadingFiles ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        {selectedFile && (
                            <Button size="sm" onClick={handleSave} disabled={isSaving || isLoadingContent}>
                                {isSaving ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <SaveIcon className="w-4 h-4 mr-2" />}
                                Save Changes
                            </Button>
                        )}
                    </div>
                </HeaderContainer>

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-4 h-[calc(100vh-250px)] min-h-[500px]">
                    {/* File Explorer */}
                    <Card className="lg:col-span-1 p-0 flex flex-col h-full overflow-hidden border">
                        <div className="p-3 border-b bg-muted/40 flex items-center gap-2">
                            <Button 
                                variant="ghost" 
                                size="icon" 
                                className="h-8 w-8" 
                                onClick={handleGoUp}
                                disabled={currentPath === '/' || currentPath === ''}
                            >
                                <CornerLeftUpIcon className="w-4 h-4" />
                            </Button>
                            <Input 
                                value={currentPath} 
                                readOnly 
                                className="h-8 text-xs font-mono bg-background" 
                            />
                        </div>
                        
                        <div className="flex-1 overflow-y-auto p-2 space-y-1">
                            {isLoadingFiles ? (
                                <div className="flex items-center justify-center h-20 text-muted-foreground">
                                    <Loader2 className="w-5 h-5 animate-spin mr-2" />
                                    Loading...
                                </div>
                            ) : files.length === 0 ? (
                                <div className="flex items-center justify-center h-20 text-muted-foreground text-sm">
                                    Empty directory
                                </div>
                            ) : (
                                files.map((file) => (
                                    <div
                                        key={file.path}
                                        onClick={() => handleFileClick(file)}
                                        className={`
                                            flex items-center gap-2 px-3 py-2 rounded-md cursor-pointer text-sm transition-colors
                                            ${selectedFile?.path === file.path ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-muted'}
                                        `}
                                    >
                                        {file.is_dir ? (
                                            <FolderIcon className="w-4 h-4 text-blue-400 shrink-0" />
                                        ) : (
                                            <FileIcon className="w-4 h-4 text-slate-400 shrink-0" />
                                        )}
                                        <span className="truncate">{file.name}</span>
                                    </div>
                                ))
                            )}
                        </div>
                    </Card>

                    {/* Editor */}
                    <Card className="lg:col-span-3 h-full border overflow-hidden flex flex-col">
                        {selectedFile ? (
                            <>
                                <div className="p-2 border-b bg-muted/20 flex justify-between items-center px-4">
                                    <span className="text-sm font-medium flex items-center gap-2">
                                        <FileIcon className="w-4 h-4" />
                                        {selectedFile.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {determineLanguage(selectedFile.name).toUpperCase()} • {((selectedFile.size / 1024) > 1024 ? (selectedFile.size / 1024 / 1024).toFixed(2) + ' MB' : (selectedFile.size / 1024).toFixed(2) + ' KB')}
                                    </span>
                                </div>
                                <div className="flex-1 relative">
                                    {isLoadingContent ? (
                                        <div className="absolute inset-0 flex items-center justify-center bg-background/50 z-10">
                                            <Loader2 className="w-8 h-8 animate-spin text-primary" />
                                        </div>
                                    ) : null}
                                    <Editor
                                        height="100%"
                                        defaultLanguage="plaintext"
                                        language={determineLanguage(selectedFile.name)}
                                        value={fileContent}
                                        theme="vs-dark"
                                        options={{
                                            minimap: { enabled: false },
                                            fontSize: 14,
                                            padding: { top: 16 },
                                            scrollBeyondLastLine: false,
                                        }}
                                        onChange={(value) => setFileContent(value || '')}
                                    />
                                </div>
                            </>
                        ) : (
                            <div className="flex flex-col items-center justify-center h-full text-muted-foreground p-8 text-center">
                                <div className="bg-muted p-4 rounded-full mb-4">
                                    <FileIcon className="w-12 h-12 opacity-50" />
                                </div>
                                <h3 className="text-lg font-medium mb-1">No file selected</h3>
                                <p className="text-sm max-w-xs">
                                    Select a file from the list on the left to view and edit its content.
                                </p>
                            </div>
                        )}
                    </Card>
                </div>
            </Container>
        </ServerLayout>
    );
}
