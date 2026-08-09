import { Form } from '@inertiajs/react';
import { SearchIcon } from '@/components/icons';

export default function SearchInput({
  action,
  placeholder = '',
  defaultValue = '',
}) {
  return (
    // {-- FORM SEARCH --}
    <Form
      action={action}
      method="GET"
      className="mx-auto mt-6 flex w-full max-w-2xl items-center gap-2 rounded-full border border-gray-200 bg-white/90 py-2 pr-2 pl-4 shadow-lg backdrop-blur-md transition focus-within:ring-2 focus-within:ring-[#B77C4C] sm:mt-8 sm:py-3 sm:pr-3 sm:pl-6"
      options={{ preserveScroll: true, preserveState: true }}
    >
      <input
        type="text"
        name="q"
        defaultValue={defaultValue}
        className="min-w-0 flex-grow bg-transparent text-sm text-gray-800 placeholder-gray-500 outline-none sm:text-base"
        placeholder={placeholder}
      />
      <button
        type="submit"
        className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[#B77C4C] px-3 py-2 text-sm font-semibold text-white transition-all duration-200 hover:cursor-pointer hover:bg-[#9e6538] sm:px-4"
      >
        <SearchIcon className="h-4 w-4" />
        Cari
      </button>
    </Form>
  );
}
