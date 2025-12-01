import { Form } from '@inertiajs/react';

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
      className="mx-auto mt-8 flex w-full max-w-2xl items-center rounded-full border border-gray-200 bg-white/90 px-6 py-3 shadow-lg backdrop-blur-md transition focus-within:ring-2 focus-within:ring-[#B77C4C]"
      options={{ preserveScroll: true, preserveState: true }}
    >
      <input
        type="text"
        name="q"
        defaultValue={defaultValue}
        className="flex-grow bg-transparent text-gray-800 placeholder-gray-500 outline-none"
        placeholder={placeholder}
      />
      <button
        type="submit"
        className="-mx-2 rounded-full bg-[#B77C4C] px-4 py-2 font-semibold text-white transition-all duration-200 hover:cursor-pointer hover:bg-[#9e6538]"
      >
        🔍 Cari
      </button>
    </Form>
  );
}
