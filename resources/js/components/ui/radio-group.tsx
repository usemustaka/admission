import * as React from "react"

import { cn } from "@/lib/utils"

type RadioGroupContextValue = {
  name: string
  value?: string
  onValueChange?: (value: string) => void
  disabled?: boolean
}

const RadioGroupContext = React.createContext<RadioGroupContextValue | null>(null)

function RadioGroup({
  className,
  value,
  onValueChange,
  disabled,
  children,
  ...props
}: Omit<React.ComponentProps<"div">, "onChange"> & {
  value?: string
  onValueChange?: (value: string) => void
  disabled?: boolean
}) {
  const name = React.useId()

  return (
    <RadioGroupContext.Provider value={{ name, value, onValueChange, disabled }}>
      <div
        role="radiogroup"
        data-slot="radio-group"
        className={cn("grid gap-3", className)}
        {...props}
      >
        {children}
      </div>
    </RadioGroupContext.Provider>
  )
}

function useRadioGroup(): RadioGroupContextValue {
  const context = React.useContext(RadioGroupContext)

  if (!context) {
    throw new Error("RadioGroupItem must be used inside RadioGroup")
  }

  return context
}

function RadioGroupItem({
  className,
  value,
  ...props
}: Omit<React.ComponentProps<"input">, "value" | "type" | "name" | "checked" | "onChange"> & {
  value: string
}) {
  const group = useRadioGroup()

  return (
    <input
      type="radio"
      data-slot="radio-group-item"
      name={group.name}
      value={value}
      checked={group.value === value}
      disabled={group.disabled || props.disabled}
      onChange={() => group.onValueChange?.(value)}
      className={cn(
        "border-neutral-400 bg-card checked:border-neutral-900 checked:bg-neutral-900 dark:checked:border-lime-400 dark:checked:bg-lime-400 hover:border-foreground focus-visible:ring-ring relative size-5 shrink-0 cursor-pointer appearance-none rounded-full border transition-colors outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50",
        "checked:after:absolute checked:after:top-1/2 checked:after:left-1/2 checked:after:size-2 checked:after:-translate-x-1/2 checked:after:-translate-y-1/2 checked:after:rounded-full checked:after:bg-white dark:checked:after:bg-neutral-900 checked:after:content-['']",
        className
      )}
      {...props}
    />
  )
}

export { RadioGroup, RadioGroupItem }
