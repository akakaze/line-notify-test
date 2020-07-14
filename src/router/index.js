import Vue from "vue"
import VueRouter from "vue-router"
import Login from "@/views/Login.vue"
import SubAndNotify from "@/views/SubAndNotify.vue"

Vue.use(VueRouter)

const routes = [
  {
    path: "/",
    name: "Login",
    component: Login
  },
  {
    path: "/sub-and-notify",
    name: "SubAndNotify",
    component: SubAndNotify
  },
]

const router = new VueRouter({
  mode: "history",
  base: process.env.BASE_URL,
  routes,
})

export default router
