import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border border-transparent px-2.5 py-0.5 text-xs font-semibold w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring transition-colors overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "bg-lime-100 text-lime-800 [a&]:hover:bg-lime-200 dark:bg-lime-400/15 dark:text-lime-300",
        secondary:
          "bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900",
        destructive:
          "bg-red-600 text-white [a&]:hover:bg-red-700",
        outline:
          "border-neutral-300 text-foreground [a&]:hover:border-foreground dark:border-neutral-600",
        success: "bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300",
        warning: "bg-amber-50 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300",
        info: "bg-sky-50 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300",
        danger: "bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300",
        muted: "bg-neutral-100 text-neutral-700 dark:bg-neutral-700/50 dark:text-neutral-300",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
