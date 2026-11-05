import * as React from "react"

import { cn } from "@/lib/utils"

function Progress({
  className,
  value = 0,
  max = 100,
  ...props
}: React.ComponentProps<"div"> & { value?: number; max?: number }) {
  const percentage = Math.min(100, Math.max(0, (value / max) * 100))

  return (
    <div
      role="progressbar"
      aria-valuemin={0}
      aria-valuemax={max}
      aria-valuenow={value}
      data-slot="progress"
      className={cn("bg-sage-100 dark:bg-muted h-2 w-full overflow-hidden rounded-full", className)}
      {...props}
    >
      <div
        data-slot="progress-indicator"
        className="bg-primary h-full rounded-full transition-[width] duration-500"
        style={{ width: `${percentage}%` }}
      />
    </div>
  )
}

export { Progress }
