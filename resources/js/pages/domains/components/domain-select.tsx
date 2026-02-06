import { useState, useEffect, useRef } from 'react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { CheckIcon, ChevronsUpDownIcon } from 'lucide-react';
import { Command, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';

interface Domain {
  id: number;
  domain: string;
  dns_provider_id: number;
  project_id: number;
}

interface DomainSelectProps {
  value: string;
  onValueChange?: (domainId: string, domainName: string) => void;
  id?: string;
  placeholder?: string;
  className?: string;
}

export default function DomainSelect({ value, onValueChange, id, placeholder = 'Select domain...', className }: DomainSelectProps) {
  const page = usePage<SharedData>();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [selected, setSelected] = useState<string>(value);
  const loadMoreRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setSelected(value);
  }, [value]);

  // Debounce query input
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      setDebouncedQuery(query);
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [query]);

  const projectId = page.props.auth.currentProject?.id;

  const { data, isFetching, fetchNextPage, hasNextPage, isFetchingNextPage } = useInfiniteQuery<Domain[]>({
    queryKey: ['domains', projectId, debouncedQuery],
    queryFn: async ({ pageParam = 1 }) => {
      if (!projectId) return [];
      const response = await axios.get(route('api.projects.domains', { project: projectId, page: pageParam }));
      return response.data.data || [];
    },
    enabled: open && !!projectId,
    staleTime: Infinity,
    gcTime: 1000 * 60 * 5,
    refetchOnMount: false,
    refetchOnWindowFocus: false,
    initialPageParam: 1,
    getNextPageParam: (lastPage, allPages) => {
      if (!lastPage || !Array.isArray(lastPage)) {
        return undefined;
      }
      return lastPage.length === 25 ? allPages.length + 1 : undefined;
    },
  });

  const domains = data?.pages.flat() ?? [];

  useEffect(() => {
    if (!open || !hasNextPage) return;

    let observer: IntersectionObserver | null = null;
    const timeoutId = setTimeout(() => {
      if (!loadMoreRef.current) return;

      observer = new IntersectionObserver(
        (entries) => {
          const [entry] = entries;
          if (entry.isIntersecting && hasNextPage && !isFetchingNextPage) {
            fetchNextPage();
          }
        },
        { threshold: 0.1 },
      );

      observer.observe(loadMoreRef.current);
    }, 100);

    return () => {
      clearTimeout(timeoutId);
      if (observer) {
        observer.disconnect();
      }
    };
  }, [open, hasNextPage, isFetchingNextPage, fetchNextPage, debouncedQuery, domains.length]);

  const handleOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
    if (!isOpen) {
      const commandList = document.querySelector('[data-slot="command-list"]');
      if (commandList instanceof HTMLElement) {
        commandList.scrollTop = 0;
      }
      setQuery('');
    }
  };

  const selectedDomain = domains.find((domain) => String(domain.id) === selected);

  const handleSelect = (domain: Domain, currentValue: string) => {
    const newSelected = currentValue === selected ? '' : currentValue;
    setSelected(newSelected);
    setOpen(false);

    if (onValueChange) {
      onValueChange(newSelected, domain.domain);
    }
  };

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <Button id={id} variant="outline" role="combobox" aria-expanded={open} className={cn('w-full justify-between', className)}>
          {selectedDomain ? selectedDomain.domain : placeholder}
          <ChevronsUpDownIcon className="ml-2 size-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="flex max-h-[400px] w-56 flex-col p-0" align="start">
        <Command shouldFilter={false} className="flex flex-col overflow-hidden">
          <CommandInput placeholder="Search domain..." value={query} onValueChange={setQuery} />
          <CommandList data-slot="command-list" className="min-h-0 flex-1 overflow-y-auto" onWheel={(e) => e.stopPropagation()}>
            {domains.length === 0 ? (
              <div className="text-muted-foreground py-6 text-center text-sm">
                {isFetching ? 'Loading...' : query === '' ? 'No domains found' : 'No domains match your search.'}
              </div>
            ) : (
              <CommandGroup>
                {domains.map((domain: Domain) => {
                  const domainValue = String(domain.id);
                  return (
                    <CommandItem
                      key={`domain-select-${domain.id}`}
                      value={domainValue}
                      onSelect={() => handleSelect(domain, domainValue)}
                      className="truncate"
                    >
                      {domain.domain}
                      <CheckIcon className={cn('ml-auto', selected === domainValue ? 'opacity-100' : 'opacity-0')} />
                    </CommandItem>
                  );
                })}
                {hasNextPage && (
                  <div ref={loadMoreRef} className="flex justify-center py-2">
                    {isFetchingNextPage ? (
                      <span className="text-muted-foreground text-xs">Loading more...</span>
                    ) : (
                      <span className="text-muted-foreground text-xs">Scroll for more</span>
                    )}
                  </div>
                )}
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
