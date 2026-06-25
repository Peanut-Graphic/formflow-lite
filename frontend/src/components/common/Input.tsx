import { useId } from 'react';
import type { InputHTMLAttributes, ReactNode } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helpText?: string;
  icon?: ReactNode;
  leftIcon?: ReactNode;
}

export default function Input({ label, error, helpText, icon, leftIcon, id, className = '', ...props }: InputProps) {
  const displayIcon = icon || leftIcon;
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-error`;
  const helpId = `${inputId}-help`;
  const describedBy = [error ? errorId : null, helpText && !error ? helpId : null]
    .filter(Boolean)
    .join(' ') || undefined;

  return (
    <div className="w-full">
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
          {label}
          {props.required && <span className="text-red-500 ml-1" aria-hidden="true">*</span>}
        </label>
      )}
      <div className="relative">
        {displayIcon && (
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            {displayIcon}
          </div>
        )}
        <input
          id={inputId}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          aria-required={props.required ? true : undefined}
          className={`
            w-full rounded-lg border transition-colors
            ${displayIcon ? 'pl-10' : 'px-3'} py-2
            bg-white dark:bg-slate-800
            ${error
              ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
              : 'border-slate-300 dark:border-slate-600 focus:border-teal-500 focus:ring-teal-500'}
            focus:outline-none focus:ring-2 focus:ring-offset-0
            disabled:bg-slate-50 dark:disabled:bg-slate-900 disabled:text-slate-500
            text-slate-900 dark:text-white placeholder-slate-400
            ${className}
          `}
          {...props}
        />
      </div>
      {error && <p id={errorId} role="alert" className="mt-1 text-sm text-red-600">{error}</p>}
      {helpText && !error && <p id={helpId} className="mt-1 text-sm text-slate-500 dark:text-slate-400">{helpText}</p>}
    </div>
  );
}
