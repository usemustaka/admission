import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "border-input placeholder:text-neutral-500 bg-card flex min-h-20 w-full rounded-xl border px-3.5 py-3 text-base transition-[color,border-color,box-shadow] outline-none hover:border-neutral-500 disabled:cursor-not-allowed disabled:opacity-50 md:text-[15px]",
        "focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring",
        "aria-invalid:border-destructive aria-invalid:ring-destructive/30",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
