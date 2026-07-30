"use client"

import * as React from "react"
import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
} from "lucide-react"
import { Toaster as Sonner, type ToasterProps } from "sonner"

// This app manages appearance itself via `useAppearance`/localStorage (see
// resources/js/hooks/use-appearance.ts) rather than next-themes. Derive the
// toast theme from the `dark` class the appearance script/hook toggles on
// <html>, and watch for changes so switching the theme (or "system"
// following an OS change) updates already-mounted toasts.
function useDocumentTheme(): ToasterProps["theme"] {
  const getTheme = (): ToasterProps["theme"] =>
    typeof document !== "undefined" &&
    document.documentElement.classList.contains("dark")
      ? "dark"
      : "light"

  const [theme, setTheme] = React.useState<ToasterProps["theme"]>(getTheme)

  React.useEffect(() => {
    const root = document.documentElement
    const observer = new MutationObserver(() => setTheme(getTheme()))
    observer.observe(root, { attributes: true, attributeFilter: ["class"] })
    return () => observer.disconnect()
  }, [])

  return theme
}

const Toaster = ({ ...props }: ToasterProps) => {
  const theme = useDocumentTheme()

  return (
    <Sonner
      theme={theme}
      className="toaster group"
      icons={{
        success: <CircleCheckIcon className="size-4" />,
        info: <InfoIcon className="size-4" />,
        warning: <TriangleAlertIcon className="size-4" />,
        error: <OctagonXIcon className="size-4" />,
        loading: <Loader2Icon className="size-4 animate-spin" />,
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
        } as React.CSSProperties
      }
      {...props}
    />
  )
}

export { Toaster }
