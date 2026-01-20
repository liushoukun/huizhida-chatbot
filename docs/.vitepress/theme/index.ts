import DefaultTheme from 'vitepress/theme'
import { useMermaidPanZoom } from 'vitepress-plugin-mermaid-pan-zoom'
import 'vitepress-plugin-mermaid-pan-zoom/dist/style.css'

export default {
  ...DefaultTheme,
  setup() {
    useMermaidPanZoom()
    
  
  }
}