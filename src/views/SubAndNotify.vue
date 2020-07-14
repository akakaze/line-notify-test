<template>
  <v-main>
    <v-container
      class="fill-height"
      fluid
    >
      <v-row
        align="center"
        justify="center"
      >
        <v-col
          cols="12"
          sm="8"
          md="4"
        >
          <v-card class="elevation-12">
            <v-toolbar
              color="dark"
              dark
              flat
            >
              <v-toolbar-title>訂閱與通知介面</v-toolbar-title>
              <v-spacer />
              <v-btn
                v-if="isSub"
                href="../back-end/revoke"
                color="error"
              >
                取消訂閱
              </v-btn>
              <v-btn
                v-else
                :href="subUrl"
                color="success"
              >
                開始訂閱
              </v-btn>
              <v-btn
                class="ml-2"
                outlined
                href="../back-end/simulation-logout"
                color="dark"
              >
                登出
              </v-btn>
            </v-toolbar>
            <v-card-text>
              <v-form
                ref="form"
              >
                <v-select
                  v-model="classv"
                  :items="['A','B','C']"
                  name="class"
                  label="Class"
                />
                <v-text-field
                  v-model="message"
                  :counter="100"
                  label="message"
                  name="message"
                  required
                />
              </v-form>
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn
                color="primary"
                @click="notify"
              >
                傳送
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </v-main>
</template>

<script>
export default {
  data() {
    return {
      isSub: false,
      subUrl: "",
      isUploading: false,
      classv: "",
      message: ""
    }
  },
  methods: {
    async notify() {
      let formData = new FormData(this.$refs.form.$el);
      this.isUploading = true;
      let result = await fetch("../back-end/notify", {
        method: 'POST',
        body: formData,
      })
      .then(res => res.text());
      this.isUploading = false;
      console.log(result);
    }
  },
  created() {
    fetch("../back-end/is-sub")
    .then(res => res.json())
    .then(sub => {
      this.isSub = sub["is-sub"];
    });
    fetch("../back-end/authorize")
    .then(res => res.text())
    .then(url => {
      this.subUrl = url;
    });
  }
}
</script>