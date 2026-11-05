import * as React from "react"

import { cn } from "@/lib/utils"

type SwitchProps = Omit<React.ComponentProps<"button">, "onChange"> & {
  checked?: boolean
  onCheckedChange?: (checked: boolean) => void
}

function Switch({
  className,
  checked = false,
  onCheckedChange,
  disabled,
  ...props
}: SwitchProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      data-slot="switch"
      data-state={checked ? "checked" : "unchecked"}
      disabled={disabled}
      onClick={() => onCheckedChange?.(!checked)}
      className={cn(
        "peer inline-flex h-7 w-12 shrink-0 items-center rounded-full border-2 border-transparent transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50",
        "data-[state=checked]:bg-neutral-900 dark:data-[state=checked]:bg-lime-400 data-[state=unchecked]:bg-neutral-300 dark:data-[state=unchecked]:bg-neutral-600",
        className
      )}
      {...props}
    >
      <span
        data-slot="switch-thumb"
        className="pointer-events-none block size-6 rounded-full bg-white shadow transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0"
        data-state={checked ? "checked" : "unchecked"}
      />
    </button>
  )
}

export { Switch }
