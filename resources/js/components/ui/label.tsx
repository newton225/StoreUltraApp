import * as LabelPrimitive from "@radix-ui/react-label"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Whether the field this label is for must be filled in (the `required` attribute of the control).
 * The label looks at its control and follows the attribute, so a required field is marked with an
 * asterisk everywhere without each form having to say it twice (playbook UI-113).
 */
function useControlRequired(htmlFor?: string): boolean {
  const [required, setRequired] = React.useState(false)

  React.useEffect(() => {
    const control = htmlFor ? document.getElementById(htmlFor) : null

    if (!control) {
      setRequired(false)

      return
    }

    const read = () =>
      setRequired("required" in control && Boolean(control.required))

    read()

    const observer = new MutationObserver(read)
    observer.observe(control, { attributes: true, attributeFilter: ["required"] })

    return () => observer.disconnect()
  }, [htmlFor])

  return required
}

function Label({
  className,
  children,
  htmlFor,
  ...props
}: React.ComponentProps<typeof LabelPrimitive.Root>) {
  const required = useControlRequired(htmlFor)

  return (
    <LabelPrimitive.Root
      data-slot="label"
      htmlFor={htmlFor}
      className={cn(
        "text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    >
      {children}
      {required && (
        <span aria-hidden="true" className="ml-0.5 text-red-500">
          *
        </span>
      )}
    </LabelPrimitive.Root>
  )
}

export { Label }
