import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'
// https://vitepress.dev/reference/site-config
export default withMermaid(defineConfig({
  title: "汇智答-智能客服平台",
  description: "汇聚智能，有问必答",
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: '需求文档', link: '/requirements' }
    ],

    sidebar: [
      
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/liushoukun/huizhida-chatbot' }
    ]
  },
  vite:{
    optimizeDeps:{
      include:[
        "mermaid",
        "dayjs",
      ],
        exclude: ["vitepress"],
    },
    ssr:{
      noExternal:['mermaid']
    }
  },
  mermaid: {

  },
  mermaidPlugin:{
    class:"mermaid"
  },
 
}))
