<template>
  <div>
    <Toolbar
      :handle-reset="resetForm"
      :handle-submit="onSendForm"
    ></Toolbar>
    <CourseCategoryForm
      ref="createForm"
      :errors="violations"
      :values="item"
    />
    <Loading :visible="isLoading" />
  </div>
</template>

<script>
import { mapActions } from "vuex"
import { createHelpers } from "vuex-map-fields"
import CourseCategoryForm from "../../components/coursecategory/Form.vue"
import Loading from "../../components/Loading.vue"
import Toolbar from "../../components/Toolbar.vue"
import CreateMixin from "../../mixins/CreateMixin"

const servicePrefix = "CourseCategory"

const { mapFields } = createHelpers({
  getterType: "coursecategory/getField",
  mutationType: "coursecategory/updateField",
})

export default {
  name: "CourseCategoryCreate",
  servicePrefix,
  mixins: [CreateMixin],
  components: {
    Loading,
    Toolbar,
    CourseCategoryForm,
  },
  data() {
    return {
      item: {},
    }
  },
  computed: {
    ...mapFields(["error", "isLoading", "created", "violations"]),
  },
  methods: {
    ...mapActions("coursecategory", ["create", "reset"]),
  },
}
</script>
